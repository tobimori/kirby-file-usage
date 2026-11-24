<?php

namespace tobimori\FileUsage;

use Kirby\Cms\File;
use Kirby\Form\Field\DisplayField;
use Kirby\Toolkit\Str;

/**
 * Lists the pages that use the file, for file blueprints
 */
final class FileUsageField extends DisplayField
{
	public function label(): string
	{
		$label = parent::label();

		// blueprints set the label from the field name if it has none
		return $label === Str::label($this->name()) ? $this->i18n('file-usage.label') : $label;
	}

	public function props(): array
	{
		$model = $this->model();

		return [
			...parent::props(),
			'items' => $model instanceof File ? FileUsage::items($model) : []
		];
	}
}
