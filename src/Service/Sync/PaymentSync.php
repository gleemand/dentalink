<?php

namespace App\Service\Sync;

use App\Service\Dentalink\ClientInterface;
use App\Service\Dentalink\Factory\FactoryInterface;
use App\Service\Simla\ApiWrapperInterface;
use App\Service\SinceDateTime\SinceDateTime;
use App\Service\SinceDateTime\SinceDateTimeInterface;
use App\Service\Transformer\TransformerInterface;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Model\Entity\Orders\Order;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class PaymentSync implements PaymentSyncInterface
{
    private ClientInterface $dentalink;
    private ApiWrapperInterface $simla;
    private SinceDateTimeInterface $sinceDateTime;
    private TransformerInterface $transformer;
    private LoggerInterface $logger;
    private array $customFields;

    public function __construct(
        FactoryInterface $factory,
        ApiWrapperInterface $simla,
        SinceDateTimeInterface $sinceDateTime,
        TransformerInterface $transformer,
        LoggerInterface $logger,
        ContainerBagInterface $params
    ) {
        $this->dentalink = $factory->create();
        $this->simla = $simla;
        $this->sinceDateTime = $sinceDateTime;
        $this->transformer = $transformer;
        $this->logger = $logger;
        $this->customFields = json_decode($params->get('app.custom_fields'), true);
    }

    public function run()
    {
        $this->logger->info('----------PaymentSync START----------');

        $this->sinceDateTime->init(SinceDateTime::PAYMENTS);
        $since = $this->sinceDateTime->get();
        $this->sinceDateTime->set();
        $this->sinceDateTime->save();
        $payments = $this->dentalink->getPayments($since);

        foreach ($payments as $payment) {
            $this->logger->debug('Payment: ' . print_r($payment, true));

            if (!$payment['monto_pago'] || !$payment['id_paciente']) {
                continue;
            }

            $orders = $this->simla->getOrdersForCustomer($payment['id_paciente'], [
                $this->customFields['fecha'] => [
                    'max' => (new \DateTime('now'))->format('Y-m-d'),
                ],
            ]);
            $this->logger->debug('Orders of customer: ' . print_r($orders, true));

            if (!$orders || !count($orders)) {
                continue;
            }

            $order = $this->getLastOrder($orders);
            $this->logger->debug('Last order: ' . print_r($order, true));

            if (!$order) {
                continue;
            }

            $transformedPayment = $this->transformer->crmPaymentTransform($payment, $order->id);

            $this->simla->paymentCreate($transformedPayment);
        }

        $this->logger->info('-----------PaymentSync END-----------');
    }

    private function getLastOrder(array $orders): ?Order
    {
        $lastOrder = null;

        foreach ($orders as $order) {
            if (!$order instanceof Order) {
                continue;
            }

            $this->logger->debug('Processing order: ' . print_r($order->customFields, true));

            if (
                !$lastOrder
                || new \DateTime($order->customFields[$this->customFields['fecha']])
                    > new \DateTime($lastOrder->customFields[$this->customFields['fecha']])
            ) {
                $lastOrder = $order;
                $this->logger->debug('1 Saving last order: ' . print_r($order->customFields, true));
            } elseif (
                new \DateTime($order->customFields[$this->customFields['fecha']])
                === new \DateTime($lastOrder->customFields[$this->customFields['fecha']])
            ) {
                if (
                    filter_var($order->customFields[$this->customFields['hora_inicio']], FILTER_SANITIZE_NUMBER_INT)
                        > filter_var($lastOrder->customFields[$this->customFields['hora_inicio']], FILTER_SANITIZE_NUMBER_INT)
                ) {
                    $lastOrder = $order;
                    $this->logger->debug('2 Saving last order: ' . print_r($order->customFields, true));
                }
            }
        }

        return $lastOrder;
    }
}