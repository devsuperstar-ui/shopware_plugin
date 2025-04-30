<?php

namespace TfcSwOzi\Core\Components\Api\Resource;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\NotFoundException;

class CarResource
{
    private EntityRepository $carRepository;

    public function __construct(EntityRepository $carRepository)
    {
        $this->carRepository = $carRepository;
    }

    public function getOne(string $id, Context $context)
    {
        $criteria = new Criteria([$id]);
        $car = $this->carRepository->search($criteria, $context)->first();

        if (!$car) {
            throw new NotFoundException("Car with ID $id not found");
        }

        return $car;
    }

    public function getList(int $offset = 0, int $limit = 25, Context $context, array $filters = [], array $sorting = [])
    {
        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);

        foreach ($filters as $field => $value) {
            $criteria->addFilter(new EqualsFilter($field, $value));
        }

        foreach ($sorting as $field => $order) {
            $criteria->addSorting(new FieldSorting($field, $order));
        }

        $result = $this->carRepository->search($criteria, $context);

        return [
            'data' => $result->getEntities(),
            'total' => $result->getTotal()
        ];
    }

    public function create(array $data, Context $context)
    {
        $this->validateCreateData($data);
        $this->checkDuplicate($data, $context);

        $id = Uuid::randomHex();
        $data['id'] = $id;

        $this->carRepository->create([$data], $context);

        return $id;
    }

    public function update(string $id, array $data, Context $context)
    {
        $this->getOne($id, $context); // Ensure the car exists.

        $data['id'] = $id;
        $this->carRepository->update([$data], $context);

        return $id;
    }

    public function delete(string $id, Context $context)
    {
        $this->getOne($id, $context); // Ensure the car exists.

        $this->carRepository->delete([['id' => $id]], $context);

        return $id;
    }

    private function validateCreateData(array $data)
    {
        $requiredFields = ['hsn', 'tsn', 'manufacturer', 'model', 'year', 'remarks'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new ApiException("Field '{$field}' is required.");
            }
        }
    }

    private function checkDuplicate(array $data, Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hsn', $data['hsn']));
        $criteria->addFilter(new EqualsFilter('tsn', $data['tsn']));

        $result = $this->carRepository->search($criteria, $context);

        if ($result->count() > 0) {
            throw new ApiException("A car with the same HSN-TSN combination already exists.");
        }
    }
}
