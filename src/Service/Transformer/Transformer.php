<?php

namespace App\Service\Transformer;

use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Customers\CustomerAddress;
use RetailCrm\Api\Model\Entity\Customers\CustomerPhone;
use RetailCrm\Api\Model\Entity\Delivery\SerializedEntityOrder;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Orders\SerializedPayment;
use RetailCrm\Api\Model\Entity\Orders\SerializedRelationCustomer;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class Transformer implements TransformerInterface
{
    private array $customFields;
    private string $site;
    private array $statusMapping;
    private string $paymentType;

    public function __construct(
        ContainerBagInterface $params
    ) {
        $this->customFields = json_decode($params->get('app.custom_fields'), true);
        $this->statusMapping = json_decode($params->get('app.status_mapping'), true);
        $this->paymentType = $params->get('crm.payment_type');
        $this->site = $params->get('crm.site');
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

        if (!empty($patient['ciudad']) || !empty($patient['comuna']) || !empty($patient['direccion'])) {
            $customer->address         = new CustomerAddress();
            $customer->address->city   = $patient['ciudad'] ?? null;
            $customer->address->region = $patient['comuna'] ?? null;
            $customer->address->text   = $patient['direccion'] ?? null;
        }

        foreach ($this->customFields as $dentalinkCode => $crmCode) {
            $customFields[$crmCode] = $patient[$dentalinkCode] ?? null;
        }

        $customer->customFields = array_filter($customFields);

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
        $order->email           = $appointment['patient']['email'] ?? null;
        $order->site            = $this->site;
        $order->status          = $this->statusMapping[$appointment['id_estado']] ?? null;
        $order->customer        = SerializedRelationCustomer::withExternalId(
            $appointment['id_paciente'],
            $this->site
        );
        $order->managerComment  = $appointment['comentarios'] ?? null;
        $order->createdAt       = new \DateTime($appointment['fecha_actualizacion'] ?? null);

        $customFields = [];

        foreach ($this->customFields as $dentalinkCode => $crmCode) {
            $customFields[$crmCode] = $appointment[$dentalinkCode] ?? null;
        }

        $order->customFields = array_filter($customFields);

        return $order;
    }

    public function dentalinkCustomerTransform(array $customer): array
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

        $patient = [
            'id' => $customer['externalId'] ?? null,
            'nombre' => $customer['firstName'] ?? null,
            'apellidos' => $customer['lastName'] ?? null,
            'sexo' => isset($customer['sex']) ? mb_strtoupper(mb_substr($customer['sex'], 0, 1)) : null,
            'id_genero' => $sex,
            'direccion' => ($customer['address'] ?? null)['text'] ?? null,
            'ciudad' => ($customer['address'] ?? null)['city'] ?? null,
            'email' => $customer['email'] ?? null,
            'telefono' => count($customer['phones'] ?? [])
                ? (int) filter_var(
                    reset($customer['phones']),
                    FILTER_SANITIZE_NUMBER_INT
                )
                : null,
            'fecha_nacimiento' => $customer['birthday'] ?? null,
        ];

        foreach ($this->customFields as $dentalinkCode => $crmCode) {
            $patient[$dentalinkCode] = ($customer['customFields'] ?? null)[$crmCode] ?? null;
        }

        return array_filter($patient);
    }

    public function crmPaymentTransform(array $pago, int $orderId): SerializedPayment
    {
        $payment = new SerializedPayment();
        $payment->type = $this->paymentType;
        $payment->amount = $pago['monto_pago'] ?? null;
        $payment->paidAt = new \DateTime($pago['fecha_creacion'] ?? null);
        $payment->comment = ($pago['medio_pago'] ?? null) . ' ' . ($pago['numero_referencia'] ?? null);
        $payment->order = new SerializedEntityOrder();
        $payment->order->id = $orderId;
        $payment->status = 'paid';

        return $payment;
    }

    public function dentalinkOrderTransform(array $order): array
    {
        $cita = [
            'id' => $order['externalId'] ?? null,
            'id_estado' => array_flip($this->statusMapping)[$order['status'] ?? null] ?? null,
            'id_paciente' => ($order['customer'] ?? null)['externalId'] ?? null,
            'comentario' => $order['managerComment'] ?? null,
        ];

        foreach ($this->customFields as $dentalinkCode => $crmCode) {
            $cita[$dentalinkCode] = ($order['customFields'] ?? null)[$crmCode] ?? null;
        }

        return array_filter($cita);
    }
}
