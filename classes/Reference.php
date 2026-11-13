<?php

namespace tobimori\FileUsage;

/**
 * Where a model references a file
 */
final readonly class Reference
{
	/**
	 * @param list<string> $path Field keys alternating with block types, e.g. `blocks`, `gallery`, `images`
	 * @param string|null $inactive Why the reference is not shown in the Panel: `hidden`, `condition` or `orphan`
	 * @param bool $changes Whether the reference only exists in unpublished changes
	 */
	public function __construct(
		public array $path,
		public string|null $inactive,
		public bool $changes
	) {
	}

	public function key(): string
	{
		return implode('|', [...$this->path, $this->inactive ?? '']);
	}
}
