<?php

namespace TfcSwOzi\Core\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use TfcSwOzi\Components\TfcClient;

class CustomerService
{
    private $customerRepository;
    private $tfcClient;

    public function __construct($customerRepository, TfcClient $tfcClient)
    {
        $this->customerRepository = $customerRepository;
        $this->tfcClient = $tfcClient;
    }

    public function syncCustomerWithERP(string $customerId, string $mode, Context $context): void
    {
        $customer = $this->getCustomer($customerId, $context);
        if (!$customer) {
            throw new \Exception('Customer not found');
        }

        // Prepare data for ERP sync
        $customerData = [
            'id' => $customer->getId(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'email' => $customer->getEmail(),
            'street' => $customer->getDefaultShippingAddress()?->getStreet(),
            'city' => $customer->getDefaultShippingAddress()?->getCity(),
            'zipCode' => $customer->getDefaultShippingAddress()?->getZipcode(),
        ];

        $query = http_build_query(['data' => 'account', 'mode' => $mode]);
        $this->tfcClient->call($query, 'POST', $customerData);
    }

    private function getCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$customerId]);
        return $this->customerRepository->search($criteria, $context)->first();
    }
}
