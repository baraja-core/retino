<?php

declare(strict_types=1);

namespace Baraja\Retino;


use Baraja\EcommerceStandard\DTO\OrderItemInterface;

final class Formatter
{
	public const ORDER_ITEM_TYPES = [
		OrderItemInterface::TYPE_PRODUCT,
		OrderItemInterface::TYPE_DISCOUNT,
		OrderItemInterface::TYPE_SHIPPING,
		OrderItemInterface::TYPE_BILLING,
	];


	public function normalizeOrderType(string $type): string
	{
		return in_array($type, self::ORDER_ITEM_TYPES, true)
			? $type
			: OrderItemInterface::TYPE_PRODUCT;
	}


	/**
	 * Format to ISO 8601
	 */
	public function formatDateTime(\DateTimeInterface $dateTime): string
	{
		return $dateTime->format(\DateTimeInterface::ATOM);
	}
}
