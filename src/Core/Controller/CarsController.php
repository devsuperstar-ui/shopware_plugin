<?php

namespace TfcSwOzi\Core\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class CarsController
{
    private EntityRepository $carRepository;

    public function __construct(EntityRepository $carRepository)
    {
        $this->carRepository = $carRepository;
    }

    #[Route(path:'/api/_action/cars', name: 'api._action.cars.index', methods: ['GET'])]
    public function index(Request $request, Context $context): JsonResponse
    {
        $limit = (int)$request->query->get('limit', 1000);
        $offset = (int)$request->query->get('start', 0);
        $criteria = $this->buildCriteria($request);

        $result = $this->carRepository->search($criteria, $context);

        return new JsonResponse([
            'success' => true,
            'data' => $result->getElements(),
            'total' => $result->getTotal(),
        ]);
    }

    /**
     * @Route("/api/_action/cars/{id}", name="api.action.cars.get", methods={"GET"})
     */
    public function get(string $id, Context $context): JsonResponse
    {
        $result = $this->carRepository->search(new Criteria([$id]), $context)->first();

        if (!$result) {
            return new JsonResponse(['success' => false, 'message' => 'Car not found'], 404);
        }

        return new JsonResponse(['success' => true, 'data' => $result]);
    }

    /**
     * @Route("/api/_action/cars", name="api.action.cars.post", methods={"POST"})
     */
    public function post(Request $request, Context $context): JsonResponse
    {
        try {
            $data = $request->request->all();
            $this->carRepository->create([$data], $context);

            return new JsonResponse(['success' => true, 'data' => $data], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * @Route("/api/_action/cars/{id}", name="api.action.cars.put", methods={"PUT"})
     */
    public function put(string $id, Request $request, Context $context): JsonResponse
    {
        try {
            $data = $request->request->all();
            $data['id'] = $id;
            $this->carRepository->update([$data], $context);

            return new JsonResponse(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * @Route("/api/_action/cars/{id}", name="api.action.cars.delete", methods={"DELETE"})
     */
    public function delete(string $id, Context $context): JsonResponse
    {
        try {
            $this->carRepository->delete([['id' => $id]], $context);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    private function buildCriteria(Request $request): Criteria
    {
        $criteria = new Criteria();
        $filter = $request->query->get('filter', []);
        $sort = $request->query->get('sort', []);
        $limit = (int)$request->query->get('limit', 1000);
        $offset = (int)$request->query->get('start', 0);

        if (!empty($filter)) {
            foreach ($filter as $field => $value) {
                $criteria->addFilter(new EqualsFilter($field, $value));
            }
        }

        if (!empty($sort)) {
            foreach ($sort as $field => $direction) {
                $criteria->addSorting(new FieldSorting($field, $direction));
            }
        }

        $criteria->setLimit($limit);
        $criteria->setOffset($offset);

        return $criteria;
    }
}
