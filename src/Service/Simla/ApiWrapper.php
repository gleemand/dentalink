<?php

namespace App\Service\Simla;

use Psr\Log\LoggerInterface;
use RetailCrm\Api\Client;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Factory\ClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Model\Request\BySiteRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersCreateRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersEditRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersCreateRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersEditRequest;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class ApiWrapper implements ApiWrapperInterface
{
    private Client $client;

    private string $site;

    private LoggerInterface $logger;

    public function __construct(
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->site = $params->get('crm.site');
        $this->logger = $logger;

        $apiUrl = $params->get('crm.api_url');
        $apiKey = $params->get('crm.api_key');

        $factory = new ClientFactory();
        $this->client = $factory->createClient($apiUrl, $apiKey);
    }

    public function orderGet($externalId)
    {
        try {
            $response = $this->client->orders->get(
                $externalId,
                new BySiteRequest(ByIdentifier::EXTERNAL_ID, $this->site)
            );
        } catch (ApiExceptionInterface $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API (status code: %d): %s',
                $exception->getStatusCode(),
                $exception->getMessage()
            ));

            if (count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error('Errors: ' . implode(', ', $exception->getErrorResponse()->errors));
            }

            return null;
        }

        $this->logger->debug('Order get: ' . print_r($response->order, true));

        return $response->order;
    }

    public function orderCreate($order)
    {
        $request        = new OrdersCreateRequest();
        $request->order = $order;
        $request->site  = $order->site;

        try {
            $this->client->orders->create($request);
        } catch (ApiExceptionInterface $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API (status code: %d): %s',
                $exception->getStatusCode(),
                $exception->getMessage()
            ));

            if (count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error('Errors: ' . implode(', ', $exception->getErrorResponse()->errors));
            }

            return null;
        }

        $this->logger->info('Order created: externalId#' . $order->externalId);
    }

    public function orderEdit($order)
    {
        $request        = new OrdersEditRequest();
        $request->by    = ByIdentifier::EXTERNAL_ID;
        $request->order = $order;
        $request->site  = $order->site;

        try {
            $this->client->orders->edit($order->externalId, $request);
        } catch (ApiExceptionInterface $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API (status code: %d): %s',
                $exception->getStatusCode(),
                $exception->getMessage()
            ));

            if (count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error('Errors: ' . implode(', ', $exception->getErrorResponse()->errors));
            }

            return null;
        }

        $this->logger->info('Order edited: externalId#' . $order->externalId);
    }

    public function customerGet($externalId)
    {
        try {
            $response = $this->client->customers->get(
                $externalId,
                new BySiteRequest(ByIdentifier::ID, $this->site)
            );
        } catch (ApiExceptionInterface $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API (status code: %d): %s',
                $exception->getStatusCode(),
                $exception->getMessage()
            ));

            if (count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error('Errors: ' . implode(', ', $exception->getErrorResponse()->errors));
            }

            return null;
        }

        $this->logger->debug('Customer get: ' . print_r($response->customer, true));

        return $response->customer;
    }

    public function customerCreate($customer)
    {
        $request           = new CustomersCreateRequest();
        $request->customer = $customer;
        $request->site     = $customer->site;

        try {
            $this->client->customers->create($request);
        } catch (ApiExceptionInterface $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API (status code: %d): %s',
                $exception->getStatusCode(),
                $exception->getMessage()
            ));

            if (count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error('Errors: ' . implode(', ', $exception->getErrorResponse()->errors));
            }

            return null;
        }

        $this->logger->info('Customer created: externalId#' . $customer->externalId);
    }

    public function customerEdit($customer)
    {
        $request           = new CustomersEditRequest();
        $request->by       = ByIdentifier::ID;
        $request->customer = $customer;
        $request->site     = $customer->site;

        try {
            $this->client->customers->edit($customer->externalId, $request);
        } catch (ApiExceptionInterface $exception) {
            $this->logger->error(sprintf(
                'Error from RetailCRM API (status code: %d): %s',
                $exception->getStatusCode(),
                $exception->getMessage()
            ));

            if (count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error('Errors: ' . implode(', ', $exception->getErrorResponse()->errors));
            }

            return null;
        }

        $this->logger->info('Customer edited: externalId#' . $customer->externalId);
    }
}