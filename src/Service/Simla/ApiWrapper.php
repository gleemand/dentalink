<?php

namespace App\Service\Simla;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Client;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Enum\PaginationLimit;
use RetailCrm\Api\Factory\ClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Customers\CustomerHistory;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Orders\OrderHistory;
use RetailCrm\Api\Model\Filter\Customers\CustomerHistoryFilter;
use RetailCrm\Api\Model\Filter\Orders\OrderHistoryFilterV4Type;
use RetailCrm\Api\Model\Request\BySiteRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersCreateRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersEditRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersHistoryRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersCreateRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersEditRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersHistoryRequest;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class ApiWrapper implements ApiWrapperInterface
{
    private Client $client;

    private string $site;

    private LoggerInterface $logger;

    public function __construct(
        ClientInterface $httpClient,
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->site = $params->get('crm.site');
        $this->logger = $logger;

        $apiUrl = $params->get('crm.api_url');
        $apiKey = $params->get('crm.api_key');

        $factory = new ClientFactory();
        $factory->setHttpClient($httpClient);
        $this->client = $factory->createClient($apiUrl, $apiKey);
    }

    public function orderGet(int $externalId): ?Order
    {
        try {
            $response = $this->client->orders->get(
                $externalId,
                new BySiteRequest(ByIdentifier::EXTERNAL_ID, $this->site)
            );
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            $this->logger->error(sprintf(
                'Order: externalId#%d',
                $externalId
            ));

            return null;
        }

        $this->logger->debug(sprintf(
            'Order with externalId#%d exists',
            $externalId,
        ));

        return $response->order;
    }

    public function orderCreate(Order $order): void
    {
        $this->logger->debug('Order to create: ' . print_r($order, true));

        $request        = new OrdersCreateRequest();
        $request->order = $order;
        $request->site  = $order->site;

        try {
            $this->client->orders->create($request);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            $this->logger->error(sprintf(
                'Order: %s',
                json_encode($order)
            ));

            return;
        }

        $this->logger->info('Order created: externalId#' . $order->externalId);
    }

    public function orderEdit(Order $order): void
    {
        $this->logger->debug('Order to edit: ' . print_r($order, true));

        $request        = new OrdersEditRequest();
        $request->by    = ByIdentifier::EXTERNAL_ID;
        $request->order = $order;
        $request->site  = $order->site;

        try {
            $this->client->orders->edit($order->externalId, $request);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            $this->logger->error(sprintf(
                'Order: %s',
                json_encode($order)
            ));

            return;
        }

        $this->logger->info('Order edited: externalId#' . $order->externalId);
    }

    public function customerGet(int $externalId): ?Customer
    {
        try {
            $response = $this->client->customers->get(
                $externalId,
                new BySiteRequest(ByIdentifier::EXTERNAL_ID, $this->site)
            );
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            $this->logger->error(sprintf(
                'Customer: externalId#%d',
                $externalId
            ));

            return null;
        }

        $this->logger->debug(sprintf(
            'Customer with externalId#%d exists',
            $externalId,
        ));

        return $response->customer;
    }

    public function customerCreate(Customer $customer): void
    {
        $this->logger->debug('Customer to create: ' . print_r($customer, true));

        $request           = new CustomersCreateRequest();
        $request->customer = $customer;
        $request->site     = $customer->site;

        try {
            $this->client->customers->create($request);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            $this->logger->error(sprintf(
                'Customer: %s',
                json_encode($customer)
            ));

            return;
        }

        $this->logger->info('Customer created: externalId#' . $customer->externalId);
    }

    public function customerEdit(Customer $customer): void
    {
        $this->logger->debug('Customer to edit: ' . print_r($customer, true));

        $request           = new CustomersEditRequest();
        $request->by       = ByIdentifier::EXTERNAL_ID;
        $request->customer = $customer;
        $request->site     = $customer->site;

        try {
            $this->client->customers->edit($customer->externalId, $request);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            $this->logger->error(sprintf(
                'Customer: %s',
                json_encode($customer)
            ));

            return;
        }

        $this->logger->info('Customer edited: externalId#' . $customer->externalId);
    }

    public function customersHistory(int $sinceId): ?\Generator
    {
        $request = new CustomersHistoryRequest();
        $request->filter = new CustomerHistoryFilter();
        $request->limit = PaginationLimit::LIMIT_100;

        if ($sinceId) {
            $request->filter->sinceId = $sinceId;
        } else {
            $request->filter->startDate = new \DateTime(
                'yesterday'
            );
        }

        do {
            time_nanosleep(0, 100000000); // 10 requests per second

            try {
                $response = $this->client->customers->history($request);
            } catch (\Exception $exception) {
                $this->logger->error(sprintf(
                    'Error from RetailCRM API: %s',
                    $exception->getMessage()
                ));

                return null;
            }

            if (empty($response->history)) {
                break;
            }

            foreach ($response->history as $history) {
                if ($this->filterHistory($history)) {
                    yield $history;
                }
            }

            $request->filter->sinceId = end($response->history)->id;

            if ($request->filter->startDate) {
                $request->filter->startDate = null;
            }
        } while ($response->pagination->currentPage < $response->pagination->totalPageCount);
    }

    protected function filterHistory(CustomerHistory $change): bool
    {
        return
            (
                ('api' === $change->source && !$change->apiKey->current)
                || 'api' !== $change->source
            ) && !$change->deleted;
    }
}