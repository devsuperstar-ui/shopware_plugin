<?php

namespace TfcSwOzi\Core\Subscriber;

use Shopware\Core\Checkout\Order\Event\OrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TfcSwOzi\Service\OrderService;

class OrderEventSubscriber implements EventSubscriberInterface
{
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(OrderPlacedEvent $event): void
    {
        $orderId = $event->getOrder()->getId();
        $context = $event->getContext();
        $this->orderService->syncOrderWithERP($orderId, $context);
    }
}

