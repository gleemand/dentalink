<?php

namespace App\Service\Simla;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Client;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Enum\PaginationLimit;
use RetailCrm\Api\Factory\ClientFactory;
use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\FixExternalRow;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Orders\SerializedPayment;
use RetailCrm\Api\Model\Filter\Customers\CustomerHistoryFilter;
use RetailCrm\Api\Model\Filter\Orders\OrderFilter;
use RetailCrm\Api\Model\Filter\Orders\OrderHistoryFilterV4Type;
use RetailCrm\Api\Model\Request\BySiteRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersCreateRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersEditRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersFixExternalIdsRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersHistoryRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersCreateRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersEditRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersFixExternalIdsRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersHistoryRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersPaymentsCreateRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersRequest;
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

    public function getOrdersForCustomer(int $externalId, array $customFields): ?array
    {
        $request = new OrdersRequest();
        $request->filter = new OrderFilter();
        $request->filter->customerExternalId = $externalId;
        $request->filter->customFields = $customFields;

        try {
            $response = $this->client->orders->list($request);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

            $this->logger->error(sprintf(
                'Orders of customer with externalId#%d',
                $externalId
            ));

            return null;
        }

        return $response->orders;
    }

    public function orderGet(int $externalId, bool $byId = false): ?Order
    {
        try {
            $response = $this->client->orders->get(
                $externalId,
                new BySiteRequest($byId ? ByIdentifier::ID : ByIdentifier::EXTERNAL_ID, $this->site)
            );
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

            $this->logger->error(sprintf(
                'Order: %s#%d',
                $byId ? 'id' : 'externalId',
                $externalId
            ));

            return null;
        }

        $this->logger->debug(sprintf(
            'Order with %s#%d exists',
            $byId ? 'id' : 'externalId',
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

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

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

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

            $this->logger->error(sprintf(
                'Order: %s',
                json_encode($order)
            ));

            return;
        }

        $this->logger->info('Order edited: externalId#' . $order->externalId);
    }

    public function customerGet(int $externalId, bool $byId = false): ?Customer
    {
        try {
            $response = $this->client->customers->get(
                $externalId,
                new BySiteRequest($byId ? ByIdentifier::ID : ByIdentifier::EXTERNAL_ID, $this->site)
            );
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

            $this->logger->error(sprintf(
                'Customer: %s#%d',
                $byId ? 'id' : 'externalId',
                $externalId
            ));

            return null;
        }

        $this->logger->debug(sprintf(
            'Customer %s#%d exists',
            $byId ? 'id' : 'externalId',
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

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

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

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

            $this->logger->error(sprintf(
                'Customer: %s',
                json_encode($customer)
            ));

            return;
        }

        $this->logger->info('Customer edited: externalId#' . $customer->externalId);
    }

    public function customersHistory(?int $sinceId): ?\Generator
    {
        $request = new CustomersHistoryRequest();
        $request->filter = new CustomerHistoryFilter();
        $request->limit = PaginationLimit::LIMIT_100;

        if ($sinceId) {
            $request->filter->sinceId = $sinceId;
        } else {
            $request->filter->startDate = new \DateTime('now');
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

                if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                    $this->logger->error(sprintf(
                        'Details: %s',
                        implode(', ', $exception->getErrorResponse()->errors)
                    ));
                }

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

    public function ordersHistory(?int $sinceId): ?\Generator
    {
        $request = new OrdersHistoryRequest();
        $request->filter = new OrderHistoryFilterV4Type();
        $request->limit = PaginationLimit::LIMIT_100;

        if ($sinceId) {
            $request->filter->sinceId = $sinceId;
        } else {
            $request->filter->startDate = new \DateTime('now');
        }

        do {
            time_nanosleep(0, 100000000); // 10 requests per second

            try {
                $response = $this->client->orders->history($request);
            } catch (\Exception $exception) {
                $this->logger->error(sprintf(
                    'Error from RetailCRM API: %s',
                    $exception->getMessage()
                ));

                if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                    $this->logger->error(sprintf(
                        'Details: %s',
                        implode(', ', $exception->getErrorResponse()->errors)
                    ));
                }

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

    protected function filterHistory($change): bool
    {
        return
            (
                ('api' === $change->source && !$change->apiKey->current)
                || 'api' !== $change->source
            ) && !$change->deleted;
    }

    public function fixCustomersExternalIds(array $externalIds): void
    {
        $this->logger->debug('Fix external id for customers: ' . print_r($externalIds, true));

        $request            = new CustomersFixExternalIdsRequest();
        $request->customers = [];

        foreach ($externalIds as $id => $externalId) {
            $request->customers[] = new FixExternalRow($id, $externalId);
        }

        if (!count($request->customers)) {
            return;
        }

        try {
            $this->client->customers->fixExternalIds($request);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

            $this->logger->error(sprintf(
                'Customers: %s',
                json_encode($externalIds)
            ));

            return;
        }

        $this->logger->info('Fixed external ids for customers');
    }

    public function fixOrdersExternalIds(array $externalIds): void
    {
        $this->logger->debug('Fix external id for orders: ' . print_r($externalIds, true));

        $request = new OrdersFixExternalIdsRequest();
        $request->orders = [];

        foreach ($externalIds as $id => $externalId) {
            $request->orders[] = new FixExternalRow($id, $externalId);
        }

        if (!count($request->orders)) {
            return;
        }

        try {
            $this->client->orders->fixExternalIds($request);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

            $this->logger->error(sprintf(
                'Orders: %s',
                json_encode($externalIds)
            ));

            return;
        }

        $this->logger->info('Fixed external ids for orders');
    }

    public function paymentCreate(SerializedPayment $transformedPayment): void
    {
        $this->logger->debug('Create payment: ' . print_r($transformedPayment, true));

        $request = new OrdersPaymentsCreateRequest();
        $request->payment = $transformedPayment;
        $request->site = $this->site;

        try {
            $response = $this->client->orders->paymentsCreate($request);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API: %s',
                $exception->getMessage()
            ));

            if (method_exists($exception, 'getErrorResponse') && count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(sprintf(
                    'Details: %s',
                    implode(', ', $exception->getErrorResponse()->errors)
                ));
            }

            $this->logger->error(sprintf(
                'Request: %s',
                json_encode($request)
            ));

            return;
        }

        $this->logger->info('Payment created: ' . $response->id);
    }
}