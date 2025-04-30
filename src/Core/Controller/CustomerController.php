<?php
// src/Controller/CustomerController.php

namespace TfcSwOzi\Core\Controller;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use TfcSwOzi\Core\Service\MCustomerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route(defaults: ['_routeScope' => ['api']])]
class CustomerController extends AbstractController
{
    private MCustomerService $customerService;

    public function __construct(MCustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    #[Route('/api/wws/customers', name: 'api.wws.customer.create', methods: ['POST'])]
    public function createCustomer(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email'])) {
            return new JsonResponse(['success' => false, 'message' => 'Email is required.'], 400);
        }

        // Create or update the customer in the WWS system
        $result = $this->customerService->createOrUpdateCustomer($data, $context);

        if ($result['success']) {
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $result['data']['id'],
                    'location' => "/api/wws/customers/{$result['data']['id']}"
                ]
            ]);
        }

        return new JsonResponse(['success' => false, 'message' => $result['message']], 500);
    }
}
