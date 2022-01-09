<?php

declare(strict_types=1);

namespace Baraja\Retino;


use Baraja\EcommerceStandard\DTO\AddressInterface;
use Baraja\EcommerceStandard\DTO\CustomerInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\DTO\OrderItemInterface;

final class Hydrator
{
	private Formatter $formatter;


	public function __construct()
	{
		$this->formatter = new Formatter;
	}


	/**
	 * Hydrate standard order to defined array by Retino internal rules.
	 *
	 * @return array{
	 *    ORDER_ID: string,
	 *    CODE: string,
	 *    INVOICE_CODE: string|null,
	 *    DATE: string,
	 *    CURRENCY: array{
	 *       CODE: string
	 *    },
	 *    PACKAGE_NUMBER: string|null,
	 *    CUSTOMER: array<string, mixed>,
	 *    TOTAL_PRICE: array{
	 *       WITHOUT_VAT: float,
	 *       WITH_VAT?: float,
	 *       VAT?: float,
	 *       VAT_RATE?: float
	 *    },
	 *    ORDER_ITEMS: array<int, mixed>
	 * }
	 */
	public function hydrate(OrderInterface $order): array
	{
		$customer = $order->getCustomer();
		if ($customer === null) {
			throw new \LogicException(sprintf('Customer for order "%s" is mandatory.', $order->getNumber()));
		}

		$items = [];
		foreach ($order->getItems() as $orderItem) {
			$items[] = $this->hydrateOrderItemToArray($orderItem);
		}

		return [
			'ORDER_ID' => $order->getId(),
			'CODE' => $order->getNumber(),
			'INVOICE_CODE' => $order->getInvoiceNumber(),
			'DATE' => $this->formatter->formatDateTime($order->getInsertedDate()),
			'CURRENCY' => [
				'CODE' => $order->getCurrencyCode(),
			],
			'PACKAGE_NUMBER' => $order->getPackageNumber(),
			'CUSTOMER' => $this->hydrateCustomerToArray($order, $customer),
			'TOTAL_PRICE' => $this->hydratePrice($order->getPrice(), $order->getVatRate()),
			'ORDER_ITEMS' => array_map(static fn(array $item) => ['ITEM' => $item], $items),
		];
	}


	/**
	 * @return array{
	 *    EMAIL: string,
	 *    PHONE: string,
	 *    BILLING_ADDRESS: array<string, mixed>,
	 *    SHIPPING_ADDRESS: array<string, mixed>
	 * }
	 */
	private function hydrateCustomerToArray(OrderInterface $order, CustomerInterface $customer): array
	{
		$email = $customer->getEmail();
		$phone = $customer->getPhone();

		if ($email === null) {
			throw new \InvalidArgumentException('Customer e-mail is mandatory, but no contact given.');
		}

		$deliveryAddress = $order->getDeliveryAddress();
		if ($deliveryAddress === null) {
			throw new \InvalidArgumentException('Delivery address is mandatory.');
		}
		$paymentAddress = $order->getPaymentAddress() ?? $deliveryAddress;

		return [
			'EMAIL' => $email,
			'PHONE' => $phone,
			'BILLING_ADDRESS' => $this->hydrateAddressToArray($paymentAddress),
			'SHIPPING_ADDRESS' => $this->hydrateAddressToArray($deliveryAddress),
		];
	}


	/**
	 * @return array{
	 *    TYPE: string,
	 *    NAME: string,
	 *    CODE: string,
	 *    VARIANT_NAME: string|null,
	 *    MANUFACTURER: string|null,
	 *    AMOUNT: int|float,
	 *    UNIT: string,
	 *    WEIGHT: float|null,
	 *    UNIT_PRICE: array{
	 *       WITHOUT_VAT: float,
	 *       WITH_VAT?: float,
	 *       VAT?: float,
	 *       VAT_RATE?: float
	 *    },
	 *    TOTAL_PRICE: array{
	 *       WITHOUT_VAT: float,
	 *       WITH_VAT?: float,
	 *       VAT?: float,
	 *       VAT_RATE?: float
	 *    }
	 * }
	 */
	private function hydrateOrderItemToArray(OrderItemInterface $orderItem): array
	{
		if ($orderItem->isProductBased()) {
			$code = $orderItem->getCode();
			$variant = $orderItem->getVariant();
			$variantName = $variant !== null ? $variant->getLabel() : null;
		} else {
			$code = sprintf('virtual-%d', $orderItem->getId());
			$variantName = null;
		}

		$manufacturer = $orderItem->getManufacturer();
		$unitPrice = $orderItem->getFinalPrice();
		$totalPrice = $unitPrice * $orderItem->getAmount();

		return [
			'TYPE' => $this->formatter->normalizeOrderType($orderItem->getType()),
			'NAME' => $orderItem->getLabel(),
			'CODE' => $code,
			'VARIANT_NAME' => $variantName,
			'MANUFACTURER' => $manufacturer !== null ? $manufacturer->getName() : null,
			'AMOUNT' => $orderItem->getAmount(),
			'UNIT' => $orderItem->getUnit(),
			'WEIGHT' => $orderItem->getWeight(),
			'UNIT_PRICE' => $this->hydratePrice($unitPrice, $orderItem->getVat()),
			'TOTAL_PRICE' => $this->hydratePrice($totalPrice, $orderItem->getVat()),
		];
	}


	/**
	 * @return array{
	 *    NAME: string,
	 *    COMPANY: string|null,
	 *    STREET: string,
	 *    HOUSENUMBER: string|null,
	 *    CITY: string,
	 *    ZIP: string,
	 *    COUNTRY: string,
	 *    COMPANY_ID: string|null,
	 *    VAT_ID: string|null
	 * }
	 */
	private function hydrateAddressToArray(AddressInterface $address): array
	{
		return [
			'NAME' => $address->getPersonName(),
			'COMPANY' => $address->getCompanyName(),
			'STREET' => $address->getStreet(),
			'HOUSENUMBER' => null,
			'CITY' => $address->getCity(),
			'ZIP' => $address->getZip(),
			'COUNTRY' => $address->getCountry()->getName(),
			'COMPANY_ID' => $address->getCin(),
			'VAT_ID' => $address->getTin(),
		];
	}


	/**
	 * @return array{
	 *    WITHOUT_VAT: float,
	 *    WITH_VAT?: float,
	 *    VAT?: float,
	 *    VAT_RATE?: float
	 * }
	 */
	private function hydratePrice(float $price, float $vatRate): array
	{
		$vat = $price - $price * ($vatRate / 100);
		if (abs($vat) < 0.001) { // VAT has not been included
			return [
				'WITHOUT_VAT' => $price,
			];
		}

		return [
			'WITH_VAT' => $price,
			'WITHOUT_VAT' => $price - $vat,
			'VAT' => $vat,
			'VAT_RATE' => $vatRate,
		];
	}
}
