<?php

namespace TfcSwOzi\Core\Components;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class ModelSelect
{
    private const COOKIE_NAME = 'TfcSwOzi';
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getCars(): array
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        $index = $currentRequest->cookies->get(self::COOKIE_NAME, 0);

        return [
            '0' => ['-- bitte auswählen --', $index == 0],
            '1' => ['Mercedes 190D', $index == 1],
            '2' => ['Mercedes /8', $index == 2],
            '3' => ['BMW 2002', $index == 3],
        ];
    }

    public function filterArticles(array $context): array
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        $index = $currentRequest->cookies->get(self::COOKIE_NAME, 0);

        if (isset($context['sArticles']) && is_array($context['sArticles'])) {
            $articles = &$context['sArticles'];
            foreach ($articles as $key => $article) {
                $articleIndex = $article['artikelindex'] ?? 0;
                if ($articleIndex != $index && $articleIndex != 0) {
                    unset($articles[$key]);
                }
            }
        }

        return $context;
    }
}
