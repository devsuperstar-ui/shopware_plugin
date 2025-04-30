<?php declare(strict_types=1);

namespace TfcSwOzi\Core\Components;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

class TfcClient
{
    protected $apiUrl;
    protected $httpClient;
    protected $logger;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger, $apiUrl)
    {
        $this->apiUrl = $apiUrl;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    public function call($query, $method = 'GET', $data = [])
    {
        $url = $this->apiUrl;
        if ($query) {
            $url .= '?' . $query;
        }

        $this->logger->info('API URL: ' . $url);

        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
            ],
        ];

        if ($method === 'POST' || $method === 'PUT') {
            $options['json'] = $data;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $result = $response->getContent();
            $this->logger->info('API response: ' . $result);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return null;
        }

        return $result;
    }
}
