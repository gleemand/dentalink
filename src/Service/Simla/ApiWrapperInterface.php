<?php

namespace App\Service\Simla;

use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Orders\Order;

interface ApiWrapperInterface
{
    public function orderGet(int $externalId): ?Order;
    public function orderCreate(Order $order): void;
    public function orderEdit(Order $order): void;

    public function customerGet(int $externalId): ?Customer;
    public function customerCreate(Customer $customer): void;
    public function customerEdit(Customer $customer): void;
    public function customersHistory(int $sinceId): ?\Generator;
}