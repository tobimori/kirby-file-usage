<?php

use Kirby\Cms\File;
use Kirby\Cms\Section;
use Kirby\Toolkit\I18n;
use tobimori\FileUsage\FileUsage;

return [
	'file-usage' => [
		'props' => [
			'label' => fn (string|array|null $label = null) => I18n::translate($label, $label) ?? I18n::translate('file-usage.label')
		],
		'toArray' => function () {
			/** @var Section $this */
			$model = $this->model();

			return [
				// @mago-expect analysis:non-documented-property
				'label' => $this->label,
				'items' => $model instanceof File ? FileUsage::items($model) : []
			];
		}
	]
];
