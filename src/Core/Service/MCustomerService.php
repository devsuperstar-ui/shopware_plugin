<?php
// src/Service/MCustomerService.php

namespace TfcSwOzi\Core\Service;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;


class MCustomerService
{
    private HttpClientInterface $httpClient;
    private EntityRepository $customerRepository;

    public function __construct(HttpClientInterface $httpClient, EntityRepository $customerRepository)
    {
        $this->httpClient = $httpClient;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Creates or updates a customer by sending the data to the WWS system.
     *
     * @param array $customerData The customer data to be sent.
     * @param Context $context The Shopware context.
     * 
     * @return array The result of the operation.
     */
    public function createOrUpdateCustomer(array $customerData, Context $context): array
    {
        // Prepare the data for sending to the WWS system
        $requestData = $this->prepareCustomerData($customerData);

        try {
            // Make the API request to WWS (assuming WWS API URL is configured)
            $response = $this->httpClient->request('POST', 'https://example.wws-system.com?data=acount&mode=create', [
                'json' => $requestData
            ]);

            // Check if the response is successful
            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return [
                    'success' => true,
                    'data' => [
                        'id' => $data['id'],
                        'location' => $data['location']
                    ]
                ];
            } else {
                // Handle failed response
                return [
                    'success' => false,
                    'message' => 'Failed to send customer data to WWS. Status code: ' . $response->getStatusCode()
                ];
            }
        } catch (TransportExceptionInterface $e) {
            // Handle transport-related errors (e.g., network issues)
            return [
                'success' => false,
                'message' => 'Network error while contacting WWS: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            // Handle any other errors
            return [
                'success' => false,
                'message' => 'Error while processing customer data: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Prepares the customer data before sending it to the WWS system.
     *
     * @param array $customerData The customer data to be prepared.
     * 
     * @return array The formatted customer data.
     */
    private function prepareCustomerData(array $customerData): array
    {
        return [
            'customer' => [
                'number' => $customerData['number'],
                'id' => $customerData['id'],
                'changed' => $customerData['changed'],
                'paymentId' => $customerData['paymentId'],
                'groupKey' => $customerData['groupKey'],
                'shopId' => $customerData['shopId'],
                'priceGroupId' => $customerData['priceGroupId'] ?? null,
                'encoderName' => $customerData['encoderName'],
                'hashPassword' => $customerData['hashPassword'],
                'active' => $customerData['active'],
                'email' => $customerData['email'],
                'firstLogin' => $customerData['firstLogin'],
                'lastLogin' => $customerData['lastLogin'],
                'accountMode' => $customerData['accountMode'],
                'confirmationKey' => $customerData['confirmationKey'],
                'sessionId' => $customerData['sessionId'],
                'newsletter' => $customerData['newsletter'],
                'validation' => $customerData['validation'],
                'affiliate' => $customerData['affiliate'],
                'paymentPreset' => $customerData['paymentPreset'],
                'languageId' => $customerData['languageId'],
                'referer' => $customerData['referer'],
                'internalComment' => $customerData['internalComment'],
                'failedLogins' => $customerData['failedLogins'],
                'lockedUntil' => $customerData['lockedUntil'] ?? null,
                'salutation' => $customerData['salutation'],
                'title' => $customerData['title'] ?? null,
                'firstname' => $customerData['firstname'],
                'lastname' => $customerData['lastname'],
                'birthday' => $customerData['birthday'] ?? null,
                'doubleOptinRegister' => $customerData['doubleOptinRegister'],
                'doubleOptinEmailSentDate' => $customerData['doubleOptinEmailSentDate'] ?? null,
                'doubleOptinConfirmDate' => $customerData['doubleOptinConfirmDate'] ?? null,
                'passwordChangeDate' => $customerData['passwordChangeDate'],
                'registerOptInId' => $customerData['registerOptInId'] ?? null,
                'attribute' => $customerData['attribute'] ?? [],
                'defaultBillingAddress' => $this->prepareAddressData($customerData['defaultBillingAddress']),
                'defaultShippingAddress' => $this->prepareAddressData($customerData['defaultShippingAddress']),
                'country' => $this->prepareCountryData($customerData['country'] ?? [])
            ]
        ];
    }

    /**
     * Prepares the address data for the API request.
     *
     * @param array $addressData The address data.
     * 
     * @return array The formatted address data.
     */
    private function prepareAddressData(array $addressData): array
    {
        return [
            'id' => $addressData['id'],
            'customerId' => $addressData['customerId'],
            'company' => $addressData['company'] ?? null,
            'department' => $addressData['department'] ?? null,
            'salutation' => $addressData['salutation'],
            'firstname' => $addressData['firstname'],
            'title' => $addressData['title'] ?? null,
            'lastname' => $addressData['lastname'],
            'street' => $addressData['street'],
            'zipcode' => $addressData['zipcode'],
            'city' => $addressData['city'],
            'phone' => $addressData['phone'] ?? null,
            'vatId' => $addressData['vatId'] ?? null,
            'countryId' => $addressData['countryId'],
            'stateId' => $addressData['stateId'] ?? null
        ];
    }

    /**
     * Prepares the country data for the API request.
     *
     * @param array $countryData The country data.
     * 
     * @return array The formatted country data.
     */
    private function prepareCountryData(array $countryData): array
    {
        return [
            'id' => $countryData['id'],
            'name' => $countryData['name'],
            'iso' => $countryData['iso'],
            'isoName' => $countryData['isoName'],
            'position' => $countryData['position'],
            'description' => $countryData['description'] ?? '',
            'taxFree' => $countryData['taxFree'],
            'taxFreeUstId' => $countryData['taxFreeUstId'],
            'taxFreeUstIdChecked' => $countryData['taxFreeUstIdChecked'],
            'active' => $countryData['active'],
            'iso3' => $countryData['iso3'],
            'displayStateInRegistration' => $countryData['displayStateInRegistration'],
            'forceStateInRegistration' => $countryData['forceStateInRegistration'],
            'allowShipping' => $countryData['allowShipping'],
            'areaId' => $countryData['areaId'],
            'state' => $countryData['state'] ?? null
        ];
    }
}
