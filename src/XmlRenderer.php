<?php

declare(strict_types=1);

namespace Baraja\Retino;


final class XmlRenderer
{
	/**
	 * @param array<int, mixed> $structure
	 */
	public function render(array $structure): string
	{
		return '<?xml version="1.0" encoding="UTF-8"?>';
	}
}
