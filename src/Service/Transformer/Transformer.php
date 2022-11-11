<?php

namespace App\Service\Transformer;

use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Customers\CustomerAddress;
use RetailCrm\Api\Model\Entity\Customers\CustomerPhone;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Orders\SerializedPayment;
use RetailCrm\Api\Model\Entity\Orders\SerializedRelationCustomer;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class Transformer implements TransformerInterface
{
    private array $customFields;
    private string $site;
    private array $statusMapping;
    private string $dentalinkIdField;

    public function __construct(
        ContainerBagInterface $params
    ) {
        $this->customFields = json_decode($params->get('app.custom_fields'), true);
        $this->statusMapping = $params->get('app.status_mapping');
        $this->site = $params->get('crm.site');
        $this->dentalinkIdField = $params->get('crm.dentalink_id_field');
    }

    public function crmCustomerTransform(array $patient): Customer
    {
        $customer = new Customer();

        switch ($patient['sexo'] ?? '') {
            case 'M':
                $customer->sex = 'male';
                break;
            case 'F':
                $customer->sex = 'female';
                break;
        }

        $customer->externalId   = $patient['id'];
        $customer->firstName    = $patient['nombre'] ?? null;
        $customer->lastName     = $patient['apellidos'] ?? null;
        $customer->email        = strtolower($patient['email'] ?? '');
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
            $this->customFields['identification'] => $patient['rut'] ?? null,
        ]);

        if (!empty($patient['ciudad']) || !empty($patient['comuna']) || !empty($patient['direccion'])) {
            $customer->address         = new CustomerAddress();
            $customer->address->city   = $patient['ciudad'] ?? null;
            $customer->address->region = $patient['comuna'] ?? null;
            $customer->address->text   = $patient['direccion'] ?? null;
        }

        return $customer;
    }

    public function crmOrderTransform(array $appointment): Order
    {
        $order = new Order();

        $order->externalId      = $appointment['id'];
        $order->firstName       = $appointment['patient']['nombre'] ?? null;
        $order->lastName        = $appointment['patient']['apellidos'] ?? null;
        $order->phone           = $appointment['patient']['celular'] ?? null;
        $order->additionalPhone = $appointment['patient']['telefono'] ?? null;
        $order->email           = strtolower($appointment['patient']['email'] ?? '');
        $order->site            = $this->site;
        $order->status          = $this->statusMapping[$appointment['id_estado']] ?? null;
        $order->customer        = SerializedRelationCustomer::withExternalId(
            $appointment['id_paciente'],
            $this->site
        );
        $order->managerComment  = $appointment['comentarios'] ?? null;
        $order->createdAt       = $appointment['fecha_actualizacion'] ?? null;

        $customFields = [];

        foreach ($this->customFields as $dentalinkCode => $crmCode) {
            $customFields[$crmCode] = $appointment[$dentalinkCode] ?? null;
        }

        $order->customFields = array_filter($customFields);

        return $order;
    }

    public function dentalinkCustomerTransform(Customer $customer): array
    {
        $sex = null;

        switch ($patient['sexo'] ?? '') {
            case 'male':
                $sex = 1;
                break;
            case 'female':
                $sex = 2;
                break;
        }

        return array_filter([
            'id' => $customer->externalId,
            'nombre' => $customer->firstName,
            'apellidos' => $customer->lastName,
            'sexo' => $customer->sex ? mb_strtoupper(mb_substr($customer->sex, 0, 1)) : null,
            'id_genero' => $sex,
            'direccion' => $customer->address->text,
            'ciudad' => $customer->address->city,
            'email' => $customer->email,
            'rut' => $customer->customFields[$this->customFields['rut']] ?? null,
            'telefono' => count($customer->phones)
                ? (int) filter_var(
                    reset($customer->phones),
                    FILTER_SANITIZE_NUMBER_INT
                )
                : null,
            'fecha_nacimiento' => $customer->birthday->format('Y-m-d'),
        ]);
    }

    public function crmPaymentTransform(array $pago, int $orderId): SerializedPayment
    {
        $payment = new SerializedPayment();
        $payment->amount = $pago['monto_pago'] ?? null;
        $payment->paidAt = new \DateTime($pago['fecha_creacion'] ?? null);
        $payment->comment = ($pago['medio_pago'] ?? null) . ' ' . ($pago['numero_referencia'] ?? null);
        $payment->order->id = $orderId;

        return $payment;
    }

    public function dentalinkOrderTransform(Order $order): array
    {
        $cita = [
            'id' => $order->externalId,
            'id_estado' => array_flip($this->statusMapping)[$order->status] ?? null,
            'id_paciente' => $order->customer->externalId,
            'comentario' => $order->managerComment,
        ];

        foreach ($this->customFields as $dentalinkCode => $crmCode) {
            $cita[$dentalinkCode] = $order->customFields[$crmCode] ?? null;
        }

        return array_filter($cita);
    }
}
