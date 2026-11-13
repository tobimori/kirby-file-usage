<?php

use Kirby\Cms\File;
use tobimori\FileUsage\FileUsage;

return [
	'usageInfo' => function (): string {
		/** @var File $this */
		return FileUsage::info($this);
	}
];
