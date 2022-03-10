<?php

namespace App\Service\Transformer;

use RetailCrm\Api\Enum\Customers\CustomerType;
use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Customers\CustomerAddress;
use RetailCrm\Api\Model\Entity\Customers\CustomerPhone;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Orders\SerializedRelationCustomer;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class Transformer implements TransformerInterface
{
    private array $customFields;

    private string $site;

    public function __construct(
        ContainerBagInterface $params
    ) {
        $this->customFields = $params->get('crm.custom_fields');
        $this->site = $params->get('crm.site');
    }

    public function customerTransform(array $patient)
    {
        $customer = new Customer();

        $customer->externalId = $patient['id'];
        $customer->firstName = $patient['nombre'] ?? '';
        $customer->lastName = $patient['apellidos'] ?? '';
        $customer->phones = [
            new CustomerPhone($patient['telefono'] ?? ''),
            new CustomerPhone($patient['celular'] ?? ''),
        ];
        $customer->address = new CustomerAddress();
        $customer->address->city = $patient['ciudad'] ?? '';
        $customer->address->region = $patient['comuna'] ?? '';
        $customer->address->text = $patient['direccion'] ?? '';
        $customer->email = $patient['email'] ?? '';
        $customer->site = $this->site;

        return $customer;
    }

    public function orderTransform($appointment)
    {
        $order = new Order();

        $order->externalId = $appointment['id'];
        $order->firstName = $appointment['nombre_paciente'] ?? '';
        $order->customFields = [
            $this->customFields['date'] => $appointment['fecha'] ?? '',
            $this->customFields['time'] => $appointment['hora_inicio'] ?? ''
                . ' - ' . $appointment['hora_fin'] ?? ''
                . ' (' . $appointment['duracion'] ?? '' . ')',
        ];
        $order->customer = SerializedRelationCustomer::withExternalIdAndType(
            $appointment['id_paciente'],
            CustomerType::CUSTOMER,
            $this->site
        );
        $order->site = $this->site;

        return $order;
    }
}
