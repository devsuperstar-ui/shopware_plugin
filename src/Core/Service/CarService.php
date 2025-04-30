<?php


namespace TfcSwOzi\Core\Service;

use TfcSwOzi\Models\Repository;
use Shopware\Core\Framework\Context;

class CarService
{
    /**
     * @var Repository
     */
    private $carRepository;

    public function __construct(Repository $carRepository)
    {
        $this->carRepository = $carRepository;
    }

    public function getCarDetails($carId, Context $context)
    {
        return $this->carRepository->getCarById($carId, $context);
    }
}
