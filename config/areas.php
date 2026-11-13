<?php

use Kirby\Cms\File;
use Kirby\Cms\Find;
use Kirby\Toolkit\Escape;
use Kirby\Toolkit\I18n;
use tobimori\FileUsage\FileUsage;

// replaces only the `load` handler of the core dialogs
$delete = [
	'load' => function (string $path, string $filename) {
		/** @var File $file Find::file() throws instead of returning null */
		$file = Find::file($path, $filename);
		$count = FileUsage::count($file);
		$text = '<p>' . I18n::template('file.delete.confirm', ['filename' => Escape::html($file->filename())]) . '</p>';

		if ($count > 0) {
			$shown = array_slice(FileUsage::items($file), 0, 5);
			$list = array_map(fn ($item) => '<li>' . $item['text'] . '</li>', $shown);

			if ($more = $count - count($shown)) {
				$list[] = '<li>' . I18n::template('file-usage.more', ['count' => $more]) . '</li>';
			}

			$text .= '<p>' . (string)I18n::translateCount('file-usage.delete', $count) . '</p><ul>' . implode($list) . '</ul>';
		}

		return [
			'component' => 'k-remove-dialog',
			'props' => ['text' => $text]
		];
	}
];

return [
	'site' => [
		'dialogs' => [
			'page.file.delete' => $delete,
			'site.file.delete' => $delete
		]
	],
	'users' => [
		'dialogs' => [
			'user.file.delete' => $delete
		]
	],
	'account' => [
		'dialogs' => [
			'account.file.delete' => $delete
		]
	]
];
