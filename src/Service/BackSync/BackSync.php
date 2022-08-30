<?php

namespace App\Service\BackSync;

use App\Service\Dentalink\ClientInterface;
use App\Service\Dentalink\Factory\Factory;
use App\Service\Dentalink\Factory\FactoryInterface;
use App\Service\Simla\ApiWrapperInterface;
use App\Service\SinceDateTime\SinceDateTimeInterface;
use App\Service\SinceId\SinceIdInterface;
use App\Service\Transformer\TransformerInterface;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Model\Entity\Customers\Customer;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class BackSync implements BackSyncInterface
{
    private ClientInterface $dentalink;
    private ApiWrapperInterface $simla;
    private SinceIdInterface $sinceId;
    private TransformerInterface $transformer;
    private LoggerInterface $logger;
    private array $customFields;

    public function __construct(
        FactoryInterface $factory,
        ApiWrapperInterface $simla,
        SinceIdInterface $sinceId,
        TransformerInterface $transformer,
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->dentalink = $factory->create();
        $this->simla = $simla;
        $this->sinceId = $sinceId;
        $this->transformer = $transformer;
        $this->logger = $logger;
        $this->customFields = json_decode($params->get('crm.custom_fields'), true);
    }

    public function run()
    {
        $this->logger->info('----------BackSync START----------');
        $this->logger->info('NB! Only customers will be synced!');

        $sinceId = $this->sinceId->get();
        $this->sinceId->set($sinceId);
        $history = $this->simla->customersHistory($sinceId);

        $ids = [];

        if (is_iterable($history)) {
            foreach ($history as $change) {
                $ids[$change->customer->id]['id'] = $change->customer->id;
                $ids[$change->customer->id]['historyId'] = $change->id;
            }
        }

        foreach ($ids as $id) {
            $customer = $this->simla->customerGet($id['id']);

            if (!$customer) {
                continue;
            }

            $this->logger->debug('Customer: ' . print_r($customer, true));

            $patient = $this->transformer->dentalinkCustomerTransform($customer);

            if (!$customer->customFields[$this->customFields['dentalink_id']]) {
                $createdPatient = $this->dentalink->createPatient($patient);
                $this->simla->customerEdit($this->newCustomerWithDentalinkId($customer, $createdPatient->id));
            } else {
                $this->dentalink->editPatient($patient);
            }

            $this->sinceId->set($id['historyId']);
        }

        $this->sinceId->save();

        $this->logger->info('-----------BackSync END-----------');
    }

    private function newCustomerWithDentalinkId(Customer $customer, int $dentalinkId): Customer
    {
        $customer->customFields[$this->customFields['dentalink_id']] = $dentalinkId;

        return $customer;
    }
}