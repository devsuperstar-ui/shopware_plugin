<?php

namespace TfcSwOzi\Core\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\Event\CustomerUpdatedEvent;
use Shopware\Core\Framework\Event\CustomerDeletedEvent;
use Shopware\Core\Framework\Event\EventData\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TfcSwOzi\Service\CustomerService;

class CustomerEventSubscriber implements EventSubscriberInterface
{
    private $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerRegisterEvent::class => 'onCustomerRegister',
            CustomerUpdatedEvent::class => 'onCustomerUpdate',
            CustomerDeletedEvent::class => 'onCustomerDelete',
        ];
    }

    public function onCustomerRegister(CustomerRegisterEvent $event): void
    {
        $customerId = $event->getCustomer()->getId();
        $context = $event->getContext();
        $this->customerService->syncCustomerWithERP($customerId, 'register', $context);
    }

    public function onCustomerUpdate(CustomerUpdatedEvent $event): void
    {
        $customerId = $event->getCustomer()->getId();
        $context = $event->getContext();
        $this->customerService->syncCustomerWithERP($customerId, 'update', $context);
    }

    public function onCustomerDelete(CustomerDeletedEvent $event): void
    {
        $customerId = $event->getCustomerId();
        $context = $event->getContext();
        $this->customerService->syncCustomerWithERP($customerId, 'delete', $context);
    }
}
