<?php

namespace App\Service\BackSync;

use App\Service\Dentalink\ClientInterface;
use App\Service\Dentalink\Factory\FactoryInterface;
use App\Service\Simla\ApiWrapperInterface;
use App\Service\SinceId\SinceId;
use App\Service\SinceId\SinceIdInterface;
use App\Service\Transformer\TransformerInterface;
use Psr\Log\LoggerInterface;

class BackSync implements BackSyncInterface
{
    private const FIELDS = [
        'order' => [
            'status',
            'manager_comment',
        ],
        'customer' => [],
    ];

    private ClientInterface $dentalink;
    private ApiWrapperInterface $simla;
    private SinceIdInterface $sinceId;
    private TransformerInterface $transformer;
    private LoggerInterface $logger;
    private array $externalIds;

    public function __construct(
        FactoryInterface $factory,
        ApiWrapperInterface $simla,
        SinceIdInterface $sinceId,
        TransformerInterface $transformer,
        LoggerInterface $logger
    ) {
        $this->dentalink = $factory->create();
        $this->simla = $simla;
        $this->sinceId = $sinceId;
        $this->transformer = $transformer;
        $this->logger = $logger;
    }

    public function run()
    {
        $this->logger->info('----------BackSync START----------');

        $this->processCustomersHistory();

        $this->processOrdersHistory();

        $this->logger->info('-----------BackSync END-----------');
    }

    private function processCustomersHistory()
    {
        $this->externalIds = [];

        $this->sinceId->init(SinceId::CUSTOMERS);
        $sinceId = $this->sinceId->get();
        $history = $this->simla->customersHistory($sinceId);

        $customers = $this->assembleHistory($history, 'customer');

        foreach ($customers as $customer) {
            $this->logger->debug('Customer: ' . print_r($customer, true));

            $patient = $this->transformer->dentalinkCustomerTransform($customer);

            if (!$customer['externalId']) {
                $createdPatient = $this->dentalink->createPatient($patient);
                $this->externalIds[$customer->id] = $createdPatient['id'];
            } else {
                $this->dentalink->editPatient($patient);
            }
        }

        $this->simla->fixCustomersExternalIds($this->externalIds);
    }

    private function processOrdersHistory()
    {
        $this->externalIds = [];

        $this->sinceId->init(SinceId::ORDERS);
        $sinceId = $this->sinceId->get();
        $history = $this->simla->ordersHistory($sinceId);

        $orders = $this->assembleHistory($history, 'order');

        foreach ($orders as $order) {
            $this->logger->debug('Order: ' . print_r($order, true));

            $cita = $this->transformer->dentalinkOrderTransform($order);

            if (!$order['externalId']) {
                $createdCita = $this->dentalink->createCita($cita);
                $this->externalIds[$order->id] = $createdCita['id'];
            } else {
                $this->dentalink->editCita($cita);
            }
        }

        $this->simla->fixOrdersExternalIds($this->externalIds);
    }

    private function assembleHistory(?\Generator $history, string $entityType)
    {
        $assembledHistory = [];

        foreach ($history as $change) {
            if ($change->created) {
                $assembledHistory[$change->order->id] = $change->{$entityType};
            }

            if ($change->field && ($entityType === 'customer' || in_array($change->field, self::FIELDS[$entityType]))) {
                if ($change->field === 'status') {
                    $assembledHistory[$change->{$entityType}->id][$change->field] = $change->newValue['code'];
                } elseif (false !== strripos($change->field, 'custom_')) {
                    $assembledHistory[$change->{$entityType}->id]['customFields'][str_replace('custom_', '', $change->field)] = $change->newValue;
                } elseif (false !== strripos($change->field, 'address.')) {
                    $assembledHistory[$change->{$entityType}->id]['address'][str_replace('address.', '', $change->field)] = $change->newValue;
                } else {
                    $assembledHistory[$change->{$entityType}->id][$change->field] = $change->newValue;
                }

            }

            if ($change->{$entityType}->externalId && isset($assembledHistory[$change->{$entityType}->id])) {
                $assembledHistory[$change->{$entityType}->id]['externalId'] = $change->{$entityType}->externalId;
            }

            if (isset($change->deleted, $assembledHistory[$change->{$entityType}->id])) {
                unset($assembledHistory[$change->{$entityType}->id]);
            }

            $this->sinceId->set($change->id);
            $this->sinceId->save();
        }

        return $assembledHistory;
    }
}
