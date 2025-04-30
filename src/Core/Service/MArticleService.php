<?php
// src/Service/ArticleService.php

namespace TfcSwOzi\Core\Service;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Shopware\Core\Framework\Uuid\Uuid;

class MArticleService
{
    public function __construct(
        #[Autowire(service: 'product.repository')]
        private readonly EntityRepository $productRepository,
        #[Autowire(service: 'media.repository')]
        private readonly EntityRepository $mediaRepository,
        #[Autowire(service: 'tax.repository')]
        private readonly EntityRepository $taxRepository,
        #[Autowire(service: 'product_manufacturer.repository')]
        private readonly EntityRepository $manufacturerRepository,
        #[Autowire(service: 'category.repository')]
        private readonly EntityRepository $categoryRepository,
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository
    ) {}
    

    public function createProduct(array $payload, Context $context): array
    {
        $productNumber = $payload['mainDetail']['number'];
    

        // Check if product with same product number already exists
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));
        $existingProduct = $this->productRepository->search($criteria, $context)->first();
    
        if ($existingProduct) {
            return [
                'success' => false,
                'message' => "Product with number '$productNumber' already exists."
            ];
        }
        // If no existing product, continue with product creation
        $productId = Uuid::randomHex();
        $mainDetail = $payload['mainDetail'];
        
        // Ensure stock is capped at 99 pieces
        $stock = min((int)($mainDetail['instock'] ?? 0), 99);

        // Prepare product data
        $productData = [
            'id' => $productId,
            'productNumber' => $mainDetail['number'],
            'stock' => $stock,
            'name' => $payload['name'],
            'description' => $payload['descriptionLong'] ?? '',
            'active' => $payload['active'] ?? true,
            'ean' => $mainDetail['ean'] ?? null,
            'taxId' => $this->getTaxIdByRate($payload['tax'], $context), // 🔍 FIXED
            'price' => $this->mapPrices($mainDetail['prices']),
            'manufacturerId' => $this->getManufacturerIdByName($payload['supplier'], $context), 
            'categories' => array_map(fn($id) => ['id' => $id], $this->mapCategoryIds($payload['categories'], $context)),
            'visibilities' => [[
            'salesChannelId' => $this->getSalesChannelId($context), // 🔍 FIXED
            'visibility' => 30,
        ]],
            'weight' => (float)($mainDetail['weight'] ?? 0),
            'width' => (int)($mainDetail['width'] ?? 0),
            'length' => (int)($mainDetail['len'] ?? 0),
            'height' => (int)($mainDetail['height'] ?? 0),
        ];

        // Handle images (import if URL is provided)
        $productData['media'] = $this->importImages($payload['images'], $context);

        // Create the product in Shopware
        $this->productRepository->create([$productData], $context);

        return [
            'success' => true,
            'data' => [
                'id' => $productId,
                'location' => "/api/product/$productId"
            ]
        ];
    }

    public function deleteProduct(string $id, Context $context): array
    {
        try {
            $this->productRepository->delete([['id' => $id]], $context);
            return [
                'success' => true,
                'message' => "Product with ID $id deleted successfully."
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function updatePrice(string $articleNumber, array $prices, Context $context): array
    {
        // Fetch the product using the productNumber
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $articleNumber));
        $product = $this->productRepository->search($criteria, $context)->first();

        if (!$product) {
            return ['success' => false, 'message' => 'Product not found.'];
        }

        // Update the price
        $updatedPriceData = $this->mapPrices($prices);
        $productData = [
            'id' => $product->getId(),
            'price' => $updatedPriceData,
        ];

        try {
            $this->productRepository->update([$productData], $context);
            return [
                'success' => true, 
            ];
        }  catch (\Exception $e) {
            $this->logger->error('Price update failed', ['exception' => $e]);
            return [
                'success' => false,
                'message' => 'failed to update price.',
                'error' => $e.error()
            ];
        }

    }

    public function updateStock(string $articleNumber, int $instock, Context $context): array
    {
        // Fetch the product using the articleNumber
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $articleNumber));
        $product = $this->productRepository->search($criteria, $context)->first();

        if (!$product) {
            return ['success' => false, 'message' => 'Product not found.'];
        }

        // Update the stock level
        $productData = [
            'id' => $product->getId(),
            'stock' => min($instock, 99), // Cap stock to 99 as per requirements
        ];

        $this->productRepository->update([$productData], $context);

        return [
            'success' => true,
            'id' => $product->getId(),
        ];
    }

    private function mapPrices(array $prices): array
    {
        return array_map(function ($price) {
            $grossPrice = (float)$price['price'];
            $netPrice = $grossPrice / 1.19;  // Assuming 19% VAT rate (you could make this dynamic)
    
            return [
                'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca', // Default EUR (consider fetching dynamically)
                'gross' => $grossPrice,
                'net' => $netPrice,
                'linked' => true,
            ];
        }, $prices);
    
    }

    private function mapCategories(array $categories): array
    {
        return array_map(fn($cat) => ['id' => $cat['id']], $categories);
    }

    private function importImages(array $images, Context $context): array
    {
        $mediaData = [];
        
        foreach ($images as $image) {
            // If image is hosted on an external server, import it
            if (!empty($image['link'])) {
                // Handle media import from external URL
                $mediaData[] = [
                    'media' => [
                        'id' => Uuid::randomHex(),
                        'url' => $image['link'],
                        'title' => 'Image', // You can add a better title if needed
                    ]
                ];
            }
        }

        return $mediaData;
    }

    private function getTaxIdByRate(float $rate, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('taxRate', $rate));
        $tax = $this->taxRepository->search($criteria, $context)->first();

        if (!$tax) {
            throw new \RuntimeException("Tax rate {$rate}% not found.");
        }

        return $tax->getId();
    }

    private function getManufacturerIdByName(string $name, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $manufacturer = $this->manufacturerRepository->search($criteria, $context)->first();

        if ($manufacturer) {
            return $manufacturer->getId();
        }

        // Create manufacturer if not found
        $id = Uuid::randomHex();
        $this->manufacturerRepository->create([
            ['id' => $id, 'name' => $name]
        ], $context);

        return $id;
    }

    private function mapCategoryIds(array $categoryNames, Context $context): array
    {
        $categoryIds = [];

        // Normalize input: convert [['name' => 'X']] to ['X']
        if (!empty($categoryNames) && is_array($categoryNames[0])) {
            $categoryNames = array_column($categoryNames, 'name');
        }

        foreach ($categoryNames as $name) {
            if (!is_string($name)) {
                continue; // Skip invalid entries
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', trim($name)));
            $category = $this->categoryRepository->search($criteria, $context)->first();

            if ($category) {
                $categoryIds[] = $category->getId();
            } else {
                // Optional: log or collect missing category names
                // e.g., $this->logger->warning("Category not found: $name");
            }
        }

        return $categoryIds;
    }


    private function getSalesChannelId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addAssociation('type');
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->first();

        if (!$salesChannel) {
            throw new \RuntimeException("No sales channel found.");
        }

        return $salesChannel->getId();
    }


}
