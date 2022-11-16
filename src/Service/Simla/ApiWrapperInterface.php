<?php

namespace App\Service\Simla;

use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Orders\SerializedPayment;

interface ApiWrapperInterface
{
    public function orderGet(int $externalId, bool $byId): ?Order;
    public function orderCreate(Order $order): void;
    public function orderEdit(Order $order): void;

    public function customerGet(int $externalId, bool $byId): ?Customer;
    public function customerCreate(Customer $customer): void;
    public function customerEdit(Customer $customer): void;
    public function customersHistory(int $sinceId): ?\Generator;

    public function fixOrdersExternalIds(array $externalIds): void;
    public function fixCustomersExternalIds(array $externalIds): void;

    public function paymentCreate(SerializedPayment $transformedPayment): void;
}
