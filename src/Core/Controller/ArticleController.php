<?php

namespace TfcSwOzi\Core\Controller;


use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TfcSwOzi\Core\Service\MArticleService;

#[Route(defaults: ['_routeScope' => ['example']])]
class ArticleController extends AbstractController
{
    private MArticleService $articleService;

    public function __construct(MArticleService $articleService) {
        $this->articleService = $articleService;
    }
    
    #[Route(path:'/example/api/articles', name:'example.api.article.create',  methods: ['POST'])]
    public function create(Request $request, ?Context $context): JsonResponse
    {
        if (!$context) {
            return new JsonResponse(['success' => false, 'message' => 'Context is missing.'], 400);
        }
        
        $payload = json_decode($request->getContent(), true);
    
        if (!$payload) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON'], 400);
        }
    
        $result = $this->articleService->createProduct($payload, $context);
        return new JsonResponse($result);
    }

    #[Route(path: '/example/api/articles/{id}', name: 'example.api.article.delete', methods: ['DELETE'])]
    public function delete(string $id, Context $context): JsonResponse
    {
        $result = $this->articleService->deleteProduct($id, $context);
        return new JsonResponse($result);
    }

    #[Route(path: '/example/api/articles/{articleNumber}', name: 'example.api.article.update_price', methods: ['PUT'])]
    public function updatePrice(string $articleNumber, Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data['mainDetail']['prices'])) {
            return new JsonResponse(['success' => false, 'message' => 'Price data is required.'], 400);
        }

        // Update the price
        $result = $this->articleService->updatePrice($articleNumber, $data['mainDetail']['prices'], $context);

        if ($result['success']) {
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $result['id'],
                    'location' => "/api/articles/{$result['id']}"
                ]
            ]);
        }

        return new JsonResponse([
            'success' => false, 
            'message' => 'Failed to update price.',
            'err' => $result['.error']], 500);
    }

    #[Route('/example/api/articles/{articleNumber}', name: 'example.api.article.update_stock', methods: ['PUT'])]
    public function updateStock(string $articleNumber, Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['mainDetail']['instock'])) {
            return new JsonResponse(['success' => false, 'message' => 'Stock level (instock) is required.'], 400);
        }

        // Update the stock level
        $result = $this->articleService->updateStock($articleNumber, $data['mainDetail']['instock'], $context);

        if ($result['success']) {
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $result['id'],
                    'location' => "/api/articles/{$result['id']}"
                ]
            ]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Failed to update stock level.'], 500);
    }


    #[Route('/example/api/articles/bulk-update-stock', name: 'example.api.article.bulk_update_stock', methods: ['PUT'])]
    public function bulkUpdateStock(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['articles'])) {
            return new JsonResponse(['success' => false, 'message' => 'Articles data is required.'], 400);
        }

        // Process each article stock update
        $updatedArticles = [];
        foreach ($data['articles'] as $article) {
            if (empty($article['articleNumber']) || !isset($article['mainDetail']['instock'])) {
                continue; // Skip invalid data
            }
            
            $result = $this->articleService->updateStock($article['articleNumber'], $article['mainDetail']['instock'], $context);
            if ($result['success']) {
                $updatedArticles[] = [
                    'id' => $result['id'],
                    'location' => "/api/articles/{$result['id']}"
                ];
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => $updatedArticles,
        ]);
    }
}
