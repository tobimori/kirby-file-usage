<?php

use Kirby\Cms\File;
use Kirby\Cms\Find;
use Kirby\Panel\Ui\Dialog\RemoveDialog;
use Kirby\Toolkit\HtmlString;
use Kirby\Toolkit\I18n;
use tobimori\FileUsage\FileUsage;

// replaces only the `load` handler of the core dialogs
$delete = [
	'load' => function (string $path, string $filename) {
		/** @var File $file Find::file() throws instead of returning null */
		$file = Find::file($path, $filename);
		$count = FileUsage::count($file);
		$text = '<p>' . (string)HtmlString::translate('file.delete.confirm', ['filename' => $file->filename()]) . '</p>';

		if ($count > 0) {
			$shown = array_slice(FileUsage::models($file), 0, 5);
			$list = array_map(fn ($model) => '<li>' . $model->toSafeString('{{ model.title }}') . '</li>', $shown);

			if ($more = $count - count($shown)) {
				$list[] = '<li>' . I18n::template('file-usage.more', ['count' => $more]) . '</li>';
			}

			$text .= '<p>' . (string)I18n::translateCount('file-usage.delete', $count) . '</p><ul>' . implode($list) . '</ul>';
		}

		return new RemoveDialog(text: new HtmlString($text));
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
