<?php

namespace App\Service\Sync;

use App\Service\Dentalink\ClientInterface;
use App\Service\Dentalink\Factory\Factory;
use App\Service\Dentalink\Factory\FactoryInterface;
use App\Service\Simla\ApiWrapperInterface;
use App\Service\SinceDateTime\SinceDateTimeInterface;
use App\Service\Transformer\TransformerInterface;
use Psr\Log\LoggerInterface;

class Sync implements SyncInterface
{
    private ClientInterface $dentalink;

    private ApiWrapperInterface $simla;

    private SinceDateTimeInterface $sinceDateTime;

    private TransformerInterface $transformer;

    private LoggerInterface $logger;

    public function __construct(
        FactoryInterface $factory,
        ApiWrapperInterface $simla,
        SinceDateTimeInterface $sinceDateTime,
        TransformerInterface $transformer,
        LoggerInterface $logger
    ) {
        $this->dentalink = $factory->create();
        $this->simla = $simla;
        $this->sinceDateTime = $sinceDateTime;
        $this->transformer = $transformer;
        $this->logger = $logger;
    }

    public function run()
    {
        $this->logger->info('----------Sync START----------');

        $since = $this->sinceDateTime->get();
        $this->sinceDateTime->set();
        $appointments = $this->dentalink->getAppointments($since);

        if (is_iterable($appointments)) {
            foreach ($appointments as $appointment) {
                $appointment['patient'] = $this->dentalink->getPatient($appointment['id_paciente']);
                $customer = $this->simla->customerGet($appointment['patient']['id']);
                $order = $this->simla->orderGet($appointment['id']);

                $this->logger->debug('Appointment: ' . print_r($appointment, true));

                $transformedCustomer = $this->transformer->crmCustomerTransform($appointment['patient']);
                $transformedOrder = $this->transformer->crmOrderTransform($appointment);

                if (!$customer) {
                    $this->simla->customerCreate($transformedCustomer);
                } else {
                    $this->simla->customerEdit($transformedCustomer);
                }

                if (!$order) {
                    $this->simla->orderCreate($transformedOrder);
                } else {
                    $this->simla->orderEdit($transformedOrder);
                }
            }

            $this->sinceDateTime->save();
        }

        $this->logger->info('-----------Sync END-----------');
    }
}