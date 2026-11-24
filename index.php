<?php

// @mago-expect lint:no-error-control-operator
@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use tobimori\FileUsage\FileUsageField;

if (
	version_compare(App::version() ?? '0.0.0', '6.0.0', '<') === true ||
	version_compare(App::version() ?? '0.0.0', '7.0.0', '>=') === true
) {
	throw new Exception('Kirby File Usage requires Kirby 6');
}

App::plugin(
	'tobimori/file-usage',
	extends: [
		'fields' => [
			'file-usage' => FileUsageField::class
		],
		'areas' => require __DIR__ . '/config/areas.php',
		'fileMethods' => require __DIR__ . '/config/file-methods.php',
		'filesMethods' => require __DIR__ . '/config/files-methods.php',
		// get all files from /translations and register them as language files
		'translations' => A::keyBy(
			A::map(
				Dir::files(__DIR__ . '/translations'),
				function (string $file) {
					$translations = [];
					foreach (Json::read(__DIR__ . '/translations/' . $file) as $key => $value) {
						$translations["file-usage.{$key}"] = $value;
					}

					return A::merge(
						['lang' => F::name($file)],
						$translations
					);
				}
			),
			'lang'
		)
	]
);
