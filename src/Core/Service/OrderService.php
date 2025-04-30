<?php

namespace TfcSwOzi\Core\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\OrderEntity;
use TfcSwOzi\Components\TfcClient;

class OrderService
{
    private $orderRepository;
    private $tfcClient;

    public function __construct($orderRepository, TfcClient $tfcClient)
    {
        $this->orderRepository = $orderRepository;
        $this->tfcClient = $tfcClient;
    }

    public function syncOrderWithERP(string $orderId, Context $context): void
    {
        $order = $this->getOrder($orderId, $context);
        if (!$order) {
            throw new \Exception('Order not found');
        }

        // Prepare data for ERP sync
        $orderData = [
            'id' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'totalPrice' => $order->getAmountTotal(),
            'currency' => $order->getCurrency()?->getIsoCode(),
            'lineItems' => array_map(function ($lineItem) {
                return [
                    'productId' => $lineItem->getProductId(),
                    'quantity' => $lineItem->getQuantity(),
                    'price' => $lineItem->getUnitPrice(),
                ];
            }, $order->getLineItems()->getElements()),
        ];

        $query = http_build_query(['data' => 'order', 'mode' => 'create']);
        $this->tfcClient->call($query, 'POST', $orderData);
    }

    private function getOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$orderId]);
        $criteria->addAssociation('lineItems'); // Include associated line items
        return $this->orderRepository->search($criteria, $context)->first();
    }
}
