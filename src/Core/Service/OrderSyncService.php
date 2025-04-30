<?php
// src/Service/WwsOrderSyncService.php

namespace TfcSwOzi\Core\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OrderSyncService
{
    private const WWS_ENDPOINT = 'https://example.wws-system.com?data=order&mode=create';

    private HttpClientInterface $httpClient;
    private EntityRepository $orderRepository;


    public function __construct(
        HttpClientInterface $httpClient,
        EntityRepository $orderRepository,
        private readonly LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->orderRepository = $orderRepository;
    }

    public function sendOrderToWws(array $order, Context $context): bool
    {
        try {
            $payload = $this->buildPayload($order);
            
            $response = $this->httpClient->post(self::WWS_ENDPOINT, [
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (!empty($body['success']) && $body['success'] === 'true') {
                return true;
            }

            $this->logger->error('WWS order creation failed', ['response' => $body]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Error sending order to WWS', ['exception' => $e]);
            return false;
        }
    }

    private function buildPayload(array $order): array
    {
        return [
            'id' => $order['id'],
            'changed' => $order['changed']?->format(DATE_ATOM),
            'number' => $order['number'],
            'customerId' => $order['customerId'],
            'paymentId' => $order['paymentId'],
            'dispatchId' => $order['dispatchId'],
            'partnerId' => '',
            'shopId' => 1,
            'invoiceAmount' => $order['invoiceAmount'],
            'invoiceAmountNet' => $order['invoiceAmountNet'],
            'invoiceShipping' => $order['invoiceShipping'],
            'invoiceShippingNet' => $order['[invoiceShippingNet'] / 1.19,
            'invoiceShippingTaxRate' => 19,
            'orderTime' => $order['orderTime']?->format(DATE_ATOM),
            'transactionId' => $order['transactionId'],
            'comment' => $order['comment'] ?? '',
            'customerComment' => $order['customerComment'] ?? '',
            'internalComment' => $order['internalComment'] ?? '',
            'net' => $order['taxStatus'] === 'net' ? 1 : 0,
            'taxFree' => 0,
            'temporaryId' => '',
            'referer' => '',
            'clearedDate' => null,
            'trackingCode' => '',
            'languageIso' => $order['languageIso'],
            'currency' => $order['currency'],
            'currencyFactor' => $order['currencyFactor'],
            'remoteAddress' => '',
            'deviceType' => 'desktop',
            'isProportionalCalculation' => false,
            'details' => [], // populate with order line items if needed
            'documents' => [], // populate with docs if needed
            'payment' => [], // fill from payment method entity
            'paymentStatus' => [], // optionally add payment status
            'orderStatus' => [],
            'customer' => [], // fill with customer entity data
            'paymentInstances' => [],
            'billing' => [], // populate from billing address
            'shipping' => [], // populate from shipping address
            'shop' => [],
            'dispatch' => [],
            'attribute' => [],
            'languageSubShop' => [],
            'paymentStatusId' => $order['pamentStatusId'],
            'orderStatusId' => $order['orderStatusId'],
            'success' => true,
        ];
    }
}
