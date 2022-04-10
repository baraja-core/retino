<?php

declare(strict_types=1);

namespace Baraja\Retino;


use Baraja\EcommerceStandard\Collection\OrderCollection;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\Lock\Lock;

final class Retino
{
	private Hydrator $hydrator;

	private XmlRenderer $xmlRenderer;


	public function __construct()
	{
		$this->hydrator = new Hydrator;
		$this->xmlRenderer = new XmlRenderer;
	}


	public function processFeed(OrderCollection $orderCollection): string
	{
		Lock::wait('retino');
		Lock::startTransaction('retino', 25000);

		$data = [
			'ORDERS' => array_map(
				fn(OrderInterface $order): array => ['ORDER' => $this->hydrator->hydrate($order)],
				$orderCollection->getOrders(),
			),
		];

		/** @phpstan-ignore-next-line */
		$return = $this->xmlRenderer->render($data);

		Lock::stopTransaction('retino');

		return $return;
	}
}
