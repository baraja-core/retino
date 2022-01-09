<?php

declare(strict_types=1);

namespace Baraja\Retino;


use Baraja\EcommerceStandard\Collection\OrderCollection;
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
		$this->waitForTransaction();
		$this->startTransaction();
		$orders = [];
		foreach ($orderCollection->getOrders() as $order) {
			$orders[] = $this->hydrator->hydrate($order);
		}

		$return = $this->xmlRenderer->render(
			[
				'ORDERS' => array_map(
					static fn(array $item): array => ['ORDER' => $item],
					$orders,
				),
			],
		);

		$this->stopTransaction();

		return $return;
	}


	private function waitForTransaction(): void
	{
		if (class_exists(Lock::class)) {
			Lock::wait('retino');
		}
	}


	private function startTransaction(): void
	{
		if (class_exists(Lock::class)) {
			Lock::startTransaction('retino', 25000);
		}
	}


	private function stopTransaction(): void
	{
		if (class_exists(Lock::class)) {
			Lock::stopTransaction('retino');
		}
	}
}
