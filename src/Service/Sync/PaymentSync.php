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
        $payments = $this->dentalink->getPayments($since);

        if (is_iterable($payments)) {
            foreach ($payments as $payment) {
                if (!$payment['monto_pago'] || $payment['id_paciente']) {
                    continue;
                }

                $orders = $this->simla->getOrdersForCustomer($payment['id_paciente']);

                if (!$orders || !count($orders)) {
                    continue;
                }

                $order = $this->getLastOrder($orders);

                if (!$order) {
                    continue;
                }

                $this->logger->debug('Last order: ' . print_r($order, true));

                $transformedPayment = $this->transformer->crmPaymentTransform($payment, $order->id);

                $this->simla->paymentCreate($transformedPayment);
            }
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

            if ($lastOrder && $order->customFields[$this->customFields['fecha']] > new \DateTime('now')) {
                continue;
            }

            if (!$lastOrder || $order->customFields[$this->customFields['fecha']] > $lastOrder->customFields[$this->customFields['fecha']]) {
                $lastOrder = $order;
            } elseif ($order->customFields[$this->customFields['fecha']] === $lastOrder->customFields[$this->customFields['fecha']]) {
                if ($order->customFields[$this->customFields['hora_inicio']] > $lastOrder->customFields[$this->customFields['hora_inicio']]) {
                    $lastOrder = $order;
                }
            }
        }

        return $lastOrder;
    }
}