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

        $customer->externalId   = $patient['id'];
        $customer->firstName    = $patient['nombre'] ?? null;
        $customer->lastName     = $patient['apellidos'] ?? null;
        $customer->email        = $patient['email'] ?? null;
        $customer->site         = $this->site;
        $customer->createdAt    = !empty($patient['fecha_afiliacion'])
            ? new \DateTime($patient['fecha_afiliacion'])
            : null;
        $customer->birthday     = !empty($patient['fecha_nacimiento'])
            ? new \DateTime($patient['fecha_nacimiento'])
            : null;
        $customer->phones       = array_filter([
            !empty($patient['celular']) ? new CustomerPhone($patient['celular']) : null,
            !empty($patient['telefono']) ? new CustomerPhone($patient['telefono']) : null,
        ]);
        $customer->customFields = array_filter([
            $this->customFields['iden'] => $patient['rut'] ?? null,
        ]);

        if (!empty($patient['ciudad']) || !empty($patient['comuna']) || !empty($patient['direccion'])) {
            $customer->address         = new CustomerAddress();
            $customer->address->city   = $patient['ciudad'] ?? null;
            $customer->address->region = $patient['comuna'] ?? null;
            $customer->address->text   = $patient['direccion'] ?? null;
        }

        return $customer;
    }

    public function orderTransform($appointment)
    {
        $order = new Order();

        $order->externalId      = $appointment['id'];
        $order->firstName       = $appointment['patient']['nombre'] ?? null;
        $order->lastName        = $appointment['patient']['apellidos'] ?? null;
        $order->phone           = $appointment['patient']['celular'] ?? null;
        $order->additionalPhone = $appointment['patient']['telefono'] ?? null;
        $order->email           = $appointment['patient']['email'] ?? null;
        $order->site            = $this->site;
        $order->customer        = SerializedRelationCustomer::withExternalId(
            $appointment['id_paciente'],
            $this->site
        );
        $order->createdAt       = !empty($appointment['fecha_actualizacion'])
            ? new \DateTime($appointment['fecha_actualizacion'])
            : null;
        $order->customFields    = array_filter([
            $this->customFields['date'] => $appointment['fecha'] ?? null,
            $this->customFields['time'] => ($appointment['hora_inicio'] ?? null)
                . ' - ' . ($appointment['hora_fin'] ?? null)
                . ' (' . ($appointment['duracion'] ?? null) . ')',
        ]);

        return $order;
    }
}
