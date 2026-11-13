<?php

use Kirby\Cms\File;
use Kirby\Cms\Files;
use tobimori\FileUsage\FileUsage;

return [
	'unused' => function (): Files {
		/** @var Files $this */
		return $this->filter(static fn (File $file) => FileUsage::count($file) === 0);
	}
];
