<?php
// src/Controller/OrderController.php

namespace TfcSwOzi\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use TfcSwOzi\Core\Service\OrderSyncService;

#[Route(defaults: ['_routeScope' => ['api']])]
class OrderController extends AbstractController
{
    private OrderSyncService $orderSyncService;
    
    public function __construct(
        OrderSyncService $orderSyncService,
    ) {
        $this->orderSyncService = $orderSyncService;
    }

    #[Route(path: '/api/wws/orders', name: 'api.wws.order.create', methods: ['POST'])]
    public function syncOrder(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['success' => false, 'message' => 'Missing data'], 400);
        }

        try {
            $result = $this->orderSyncService->sendOrderToWws($data, $context);

            if ($result['success']) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Order successfully synced with WWS.',
                    'wwsResponse' => $result['response']
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Order sync with WWS failed.',
                'error' => $result['error'] ?? 'Unknown error'
            ], 500);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Exception occurred while syncing order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
