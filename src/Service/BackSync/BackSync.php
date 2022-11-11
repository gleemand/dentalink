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
        $this->sinceId->set($sinceId);
        $history = $this->simla->customersHistory($sinceId);

        $ids = [];

        if (is_iterable($history)) {
            foreach ($history as $change) {
                $ids[$change->customer->id] = $change->customer->id;

                $this->sinceId->set($change->id);
                $this->sinceId->save();
            }
        }

        foreach ($ids as $id) {
            $customer = $this->simla->customerGet($id);

            if (!$customer) {
                continue;
            }

            $this->logger->debug('Customer: ' . print_r($customer, true));

            $patient = $this->transformer->dentalinkCustomerTransform($customer);

            if (!$customer->externalId) {
                $createdPatient = $this->dentalink->createPatient($patient);
                $this->externalIds[$customer->id] = $createdPatient->id;
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
        $this->sinceId->set($sinceId);
        $history = $this->simla->ordersHistory($sinceId);

        $ids = [];

        if (is_iterable($history)) {
            foreach ($history as $change) {
                $ids[$change->order->id] = $change->order->id;

                $this->sinceId->set($change->id);
                $this->sinceId->save();
            }
        }

        foreach ($ids as $id) {
            $order = $this->simla->orderGet($id);

            if (!$order) {
                continue;
            }

            $this->logger->debug('Order: ' . print_r($order, true));

            $cita = $this->transformer->dentalinkOrderTransform($order);

            if (!$order->externalId) {
                $createdCita = $this->dentalink->createCita($cita);
                $this->externalIds[$order->id] = $createdCita->id;
            } else {
                $this->dentalink->editPatient($cita);
            }
        }

        $this->simla->fixOrdersExternalIds($this->externalIds);
    }
}