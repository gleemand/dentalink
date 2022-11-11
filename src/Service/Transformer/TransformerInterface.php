<?php

namespace App\Service\Transformer;

use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Orders\Order;

interface TransformerInterface
{
    public function crmCustomerTransform(array $patient): Customer;
    public function dentalinkCustomerTransform(Customer $customer): array;

    public function crmOrderTransform(array $appointment): Order;
    public function dentalinkOrderTransform(Order $order): array;
}
