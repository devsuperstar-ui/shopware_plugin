<?php

namespace TfcSwOzi\Models;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

class Repository
{
    /**
     * @var EntityRepository
     */
    private $carRepository;

    public function __construct(EntityRepository $carRepository)
    {
        $this->carRepository = $carRepository;
    }

    /**
     * Get Car details by ArticleId
     *
     * @param string $carId
     * @param Context $context
     * @return array|null
     */
    public function getCarById($carId, Context $context)
    {
        // Create a criteria to fetch the car by carId
        $criteria = new Criteria();
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('id', $carId));

        // Fetch the car from the repository
        $carResult = $this->carRepository->search($criteria, $context);

        return $carResult->first();  // Return the first car found, or null if no match
    }

    /**
     * Get Cars by ArticleId
     *
     * @param string $carId
     * @param Context $context
     * @return array|null
     */
    public function getCarQuery($carId, Context $context)
    {
        // You can use the same criteria to get the car details
        return $this->getCarById($carId, $context);
    }
}
