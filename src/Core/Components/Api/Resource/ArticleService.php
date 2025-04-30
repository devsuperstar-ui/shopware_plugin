<?php

namespace TfcSwOzi\Core\Components\Api\Resource;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ArticleService
{
    private EntityRepository $productRepository;
    private SalesChannelContextFactory $contextFactory;
    private string $salesChannelId;

    public function __construct(
        EntityRepository $productRepository,
        SalesChannelContextFactory $contextFactory,
        string $salesChannelId
    ) {
        $this->productRepository = $productRepository;
        $this->contextFactory = $contextFactory;
        $this->salesChannelId = $salesChannelId;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $token = Uuid::randomHex(); // Generate a unique token, or reuse one from session/request
        return $this->contextFactory->create($token, $this->salesChannelId);
    }

    /**
     * Creates or updates an article (product) while handling the lastStock flag.
     */
    public function createOrUpdateArticle(array $params)
    {
        // Apply lastStock logic
        $params = $this->customiseParams($params);

        if (isset($params['id'])) {
            return $this->update($params['id'], $params);
        }

        return $this->create($params);
    }

    private function create(array $params)
    {
        $productData = $this->prepareProductData($params);
        $this->productRepository->create([$productData], $this->getSalesChannelContext()->getContext());

        return $productData;
    }

    private function update(string $id, array $params)
    {
        $productData = $this->prepareProductData($params);
        $this->productRepository->update([$productData], $this->getSalesChannelContext()->getContext());

        return $productData;
    }

    private function prepareProductData(array $params)
    {
        $params = $this->customiseParams($params);

        return [
            'id' => $params['id'] ?? null,
            'name' => $params['name'],
            'productNumber' => $params['productNumber'],
            'stock' => $params['stock'],
            'lastStock' => $params['lastStock'] ?? false,
        ];
    }

    private function customiseParams(array $params)
    {
        if (!empty($params['lastStock'])) {
            $params['lastStock'] = true;
        }

        return $params;
    }
}
