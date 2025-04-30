<?php

namespace TfcSwOzi\Core\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiClient
{
    private HttpClientInterface $httpClient;
    private string $apiUrl;
    private string $username;
    private string $apiKey;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $apiUrl,
        string $username,
        string $apiKey
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiUrl = rtrim($apiUrl, '/') . '/';
        $this->username = $username;
        $this->apiKey = $apiKey;
    }

    public function call(string $endpoint, string $method = 'GET', array $data = [], array $params = []): array
    {
        $url = $this->apiUrl . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        try {
            $response = $this->httpClient->request(
                $method,
                $url,
                [
                    'json' => $data,
                    'auth_basic' => [$this->username, $this->apiKey],
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'Shopware ApiClient',
                    ],
                ]
            );

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            if ($statusCode >= 400) {
                $this->logger->error("API request failed: {$url}", ['status' => $statusCode, 'response' => $content]);
            }

            return $content;
        } catch (\Exception $e) {
            $this->logger->error("API call error: {$url}", ['exception' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }
}
