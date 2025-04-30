<?php

namespace TfcSwOzi\Core\Subscriber;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;

class RouteSubscriber implements EventSubscriberInterface
{
    private Connection $connection;
    private RequestStack $requestStack;

    public function __construct(Connection $connection, RequestStack $requestStack)
    {
        $this->connection = $connection;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'onCheckoutCartPageLoaded',
            ProductListingResultEvent::class => 'onProductListingResult',
            ProductLoadedEvent::class => 'onProductLoaded',
        ];
    }

    public function onCheckoutCartPageLoaded(CheckoutCartPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $cart = $event->getPage()->getCart();

        $request = $this->requestStack->getCurrentRequest();
        if ($request->query->getBoolean('isPopup')) {
            $addedProduct = [
                'productId' => $request->query->get('productId'),
                'quantity' => $request->query->getInt('quantity', 1)
            ];
            $cart->addExtension('addedProduct', $addedProduct);
        }
    }

    public function onProductListingResult(ProductListingResultEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $cookieData = $request->cookies->get('creFilterCars');

        if (!$cookieData) {
            return;
        }

        $filterCars = json_decode($cookieData, true);
        $criteria = $event->getCriteria();

        if (!empty($filterCars['supplier'])) {
            $criteria->addFilter(new EqualsFilter('manufacturer.name', $filterCars['supplier']));
        }
        if (!empty($filterCars['model'])) {
            $criteria->addFilter(new EqualsFilter('customFields.carModel', $filterCars['model']));
        }
        if (!empty($filterCars['timespan'])) {
            $criteria->addFilter(new EqualsFilter('customFields.carYear', $filterCars['timespan']));
        }
    }

    public function onProductLoaded(ProductLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $product) {
            $product->addExtension('customData', [
                'extraField' => 'Custom Value',
            ]);
        }
    }
}
