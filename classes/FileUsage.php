<?php

namespace tobimori\FileUsage;

use Generator;
use Kirby\Cms\App;
use Kirby\Cms\Fieldset;
use Kirby\Cms\Fieldsets;
use Kirby\Cms\File;
use Kirby\Cms\Languages;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Escape;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;
use Kirby\Uuid\Uuid;
use Throwable;

/**
 * Finds the site and pages that reference a file by its UUID (files fields,
 * blocks, layouts, structures and links in writer and textarea fields)
 */
final class FileUsage
{
	/**
	 * @var array<string, list<string>>|null
	 */
	private static array|null $index = null;

	/**
	 * @var array<string, array{name: string, fields: array<string, array<array-key, mixed>>}|null>
	 */
	private static array $fieldsets = [];

	/**
	 * IDs of the pages that reference the file, `''` for the site, in page tree order
	 *
	 * @return list<string>
	 */
	public static function pages(File $file): array
	{
		$id = self::id($file);

		return $id === null ? [] : self::index()[$id] ?? [];
	}

	public static function count(File $file): int
	{
		return count(self::pages($file));
	}

	/**
	 * HTML for `k-item` info, use with an unescaped `{< file.usageInfo >}` placeholder
	 */
	public static function info(File $file): string
	{
		$count = self::count($file);
		$label = Escape::html((string)I18n::translateCount('file-usage.count', $count));

		return $count === 0 ? '<span class="k-file-usage-unused">' . $label . '</span>' : $label;
	}

	/**
	 * Props for `k-item`, one item per referencing page that the user can list
	 *
	 * @return list<array{id: string, text: string, info: string, image: array<array-key, mixed>|null, link: string, status: string|null}>
	 */
	public static function items(File $file): array
	{
		$kirby = App::instance();
		$items = [];

		foreach (self::pages($file) as $id) {
			$model = $id === '' ? $kirby->site() : $kirby->page($id);

			if ($model === null || $model instanceof Page && $model->isListable() === false) {
				continue;
			}

			$panel = $model->panel();
			$references = self::references($model, (string)self::id($file));
			$places = array_unique(array_map(static fn (Reference $reference) => self::describe($model, $reference), $references));

			$items[] = [
				'id' => $id,
				'text' => $model->toSafeString('{{ model.title }}'),
				'info' => implode(', ', $places),
				'image' => $panel->image([], 'list'),
				'link' => $panel->url(true),
				'status' => $model instanceof Page ? $model->status() : null
			];
		}

		return $items;
	}

	/**
	 * Scans the raw content files, which takes milliseconds,
	 * while loading all pages with their blueprints takes a second
	 *
	 * @return array<string, list<string>>
	 */
	private static function index(): array
	{
		if (self::$index !== null) {
			return self::$index;
		}

		$kirby = App::instance();
		$root = (string)$kirby->root('content');
		$extension = '.' . $kirby->contentExtension();
		$paths = [];
		$index = [];

		foreach (Dir::index($root, true) as $path) {
			// skip folders that are not pages, e.g. `_trash` or `.git`
			if (is_string($path) && str_ends_with($path, $extension) && !preg_match('#(^|/)(\.|_(?!drafts/|changes/))#', $path)) {
				$paths[] = $path;
			}
		}

		usort($paths, static fn (string $a, string $b) => str_contains($a, '/') <=> str_contains($b, '/') ?: strnatcmp($a, $b));

		foreach ($paths as $path) {
			$files = self::ids((string)file_get_contents($root . '/' . $path));

			if ($files === []) {
				continue;
			}

			$dir = (string)preg_replace('!/?_changes$!', '', dirname($path));
			$dir = $dir === '.' ? '' : $dir;
			$name = basename($path, $extension);

			// skip metadata of files, e.g. `photo.jpg.de.txt`
			if (is_file("{$root}/{$dir}/{$name}") || is_file("{$root}/{$dir}/" . pathinfo($name, PATHINFO_FILENAME))) {
				continue;
			}

			$folders = array_filter(explode('/', $dir), static fn (string $folder) => $folder !== '' && $folder !== '_drafts');
			$id = implode('/', array_map(static fn (string $folder) => (string)preg_replace('/^\d+' . Dir::$numSeparator . '/', '', $folder), $folders));

			foreach ($files as $file) {
				$index[$file][$id] = $id;
			}
		}

		return self::$index = array_map(static fn (array $pages) => array_values($pages), $index);
	}

	/**
	 * File UUIDs in a text: `file://` in fields and `/@/file/` permalinks in writer links
	 *
	 * @return list<string>
	 */
	private static function ids(string $text): array
	{
		$matches = [];
		preg_match_all('!(?:file://|/@/file/)([a-zA-Z0-9-]+)!', $text, $matches);

		return array_values(array_unique($matches[1]));
	}

	/**
	 * The UUID of the file without scheme, `null` if UUIDs are disabled
	 */
	private static function id(File $file): string|null
	{
		return Uuid::for($file)?->id();
	}

	/**
	 * Where the model references the file, from its published content and unpublished changes
	 *
	 * @return list<Reference>
	 */
	private static function references(ModelWithContent $model, string $file): array
	{
		$references = [];

		foreach (['latest', 'changes'] as $versionId) {
			foreach (Languages::ensure() as $language) {
				foreach (self::scan($model, $model->version($versionId)->read($language) ?? []) as [$id, $path, $inactive]) {
					if ($id === $file) {
						$reference = new Reference($path, $inactive, $versionId === 'changes');
						$references[$reference->key()] ??= $reference;
					}
				}
			}
		}

		return array_values($references);
	}

	/**
	 * Human-readable location of a reference, e.g. "Content › Gallery (hidden)"
	 */
	private static function describe(ModelWithContent $model, Reference $reference): string
	{
		[$key, $blocks] = [$reference->path[0], array_slice($reference->path, 1)];

		$field = self::fields($model->blueprint()->fields())[$key] ?? null;
		$label = $field['label'] ?? null;
		$label = is_string($label) || is_array($label) ? I18n::translate($label, $label) : null;
		$path = [is_string($label) ? $label : Str::label($key)];

		foreach (array_chunk($blocks, 2) as $chunk) {
			$fieldset = self::fieldset($field, $chunk[0]);
			$path[] = $fieldset['name'] ?? Str::label($chunk[0]);
			$field = $fieldset['fields'][$chunk[1] ?? ''] ?? null;
		}

		foreach ([$reference->inactive, $reference->changes ? 'changes' : null] as $note) {
			if ($note !== null) {
				$path[array_key_last($path)] .= ' (' . I18n::template('file-usage.' . $note, $note) . ')';
			}
		}

		return Escape::html(implode(' › ', $path));
	}

	/**
	 * @param array<array-key, mixed> $content
	 * @return Generator<array{string, list<string>, string|null}>
	 */
	private static function scan(ModelWithContent $model, array $content): Generator
	{
		$fields = self::fields($model->blueprint()->fields());

		foreach ($content as $key => $value) {
			$key = (string)$key;
			yield from self::walk($value, $fields[$key] ?? null, [$key], self::inactive($fields, $key, $content));
		}
	}

	/**
	 * @param array<array-key, mixed>|null $field Blueprint props of the field that holds the value
	 * @param list<string> $path Field keys alternating with block types
	 * @return Generator<array{string, list<string>, string|null}>
	 */
	private static function walk(mixed $value, array|null $field, array $path, string|null $inactive): Generator
	{
		if (is_string($value)) {
			$files = self::ids($value);

			if ($files === []) {
				return;
			}

			// blocks and layouts are stored as JSON, nested blocks as JSON strings inside JSON
			if (in_array($value[0], ['[', '{'], true) && is_array($decoded = json_decode($value, true))) {
				yield from self::walk($decoded, $field, $path, $inactive);
				return;
			}

			foreach ($files as $file) {
				yield [$file, $path, $inactive];
			}

			return;
		}

		if (!is_array($value)) {
			return;
		}

		$type = $value['type'] ?? null;
		$content = $value['content'] ?? null;

		if (is_string($type) && is_array($content)) {
			$fields = self::fieldset($field, $type)['fields'] ?? null;
			$inactive ??= ($value['isHidden'] ?? false) === true ? 'hidden' : null;

			foreach ($content as $key => $child) {
				$key = (string)$key;
				yield from self::walk($child, $fields[$key] ?? null, [...$path, $type, $key], $inactive ?? self::inactive($fields, $key, $content));
			}

			return;
		}

		foreach ($value as $child) {
			yield from self::walk($child, $field, $path, $inactive);
		}
	}

	/**
	 * Why a field is not shown in the Panel: it is not in the blueprint
	 * anymore, or its `when` condition does not match
	 *
	 * @param array<string, array<array-key, mixed>>|null $fields
	 * @param array<array-key, mixed> $siblings
	 */
	private static function inactive(array|null $fields, string $key, array $siblings): string|null
	{
		if ($fields === null) {
			return null;
		}

		if (array_key_exists($key, $fields) === false) {
			return 'orphan';
		}

		$when = $fields[$key]['when'] ?? [];

		foreach (is_array($when) ? $when : [] as $field => $expected) {
			$field = strtolower((string)$field);
			// the Panel compares with an empty value, but block snippets usually treat a missing value like the default,
			// e.g. the `image` block without `location`
			$value = $siblings[$field] ?? $fields[$field]['default'] ?? '';

			if (self::normalize($value) !== self::normalize($expected)) {
				return 'condition';
			}
		}

		return null;
	}

	private static function normalize(mixed $value): string
	{
		return match (true) {
			is_bool($value) => $value ? 'true' : 'false',
			is_scalar($value) => (string)$value,
			default => (string)json_encode($value)
		};
	}

	/**
	 * Blueprint field props keyed by lowercase name, like content keys
	 *
	 * @param array<array-key, mixed> $fields
	 * @return array<string, array<array-key, mixed>>
	 */
	private static function fields(array $fields): array
	{
		$props = [];

		foreach ($fields as $name => $field) {
			if (is_array($field)) {
				$props[strtolower((string)$name)] = $field;
			}
		}

		return $props;
	}

	/**
	 * Resolves a block type with the fieldsets of the blocks or layout field
	 * that contains it, or with `blocks/{type}` if the field is unknown
	 *
	 * @param array<array-key, mixed>|null $field
	 * @return array{name: string, fields: array<string, array<array-key, mixed>>}|null
	 */
	private static function fieldset(array|null $field, string $type): array|null
	{
		$fieldsets = match ($field['type'] ?? null) {
			'blocks', 'layout' => is_array($field['fieldsets'] ?? null) ? $field['fieldsets'] : null,
			default => [$type]
		};

		$key = md5(serialize($fieldsets)) . $type;

		if (array_key_exists($key, self::$fieldsets)) {
			return self::$fieldsets[$key];
		}

		try {
			$fieldset = Fieldsets::factory($fieldsets)->get($type);
		} catch (Throwable) {
			$fieldset = null;
		}

		return self::$fieldsets[$key] = $fieldset instanceof Fieldset
			? ['name' => $fieldset->name(), 'fields' => self::fields($fieldset->fields())]
			: null;
	}
}
