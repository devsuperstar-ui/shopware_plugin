<?php declare(strict_types=1);

namespace TfcSwOzi;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

use Doctrine\DBAL\Exception as DBALException;

class TfcSwOzi extends Plugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onRequestEvent',
        ];
    }

    public function onRequestEvent(RequestEvent $event): void
    {
        // Logic to prepare plugin during request lifecycle
        // This could include initializing dependencies or setting up request-specific configurations
    }

    public function install(InstallContext $context): void
    {
        parent::install($context);

        $this->addCustomFields();
        $this->updateDatabaseSchema();
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if (!$context->keepUserData()) {
            $this->removeCustomFields();
            $this->dropDatabaseSchema();
        }
    }

    public function activate(ActivateContext $context): void
    {
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        parent::deactivate($context);
    }

    private function addCustomFields(): void
    {
        /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface $customFieldSetRepo */
        $customFieldSetRepo = $this->container->get('custom_field_set.repository');
        $customFieldSetCriteria = new Criteria();
        $customFieldSetCriteria->addFilter(new EqualsFilter('name', 'my_plugin_custom_fields'));
        $existingCustomFieldSets = $customFieldSetRepo->searchIds($customFieldSetCriteria, Context::createDefaultContext());
    
        if ($existingCustomFieldSets->getTotal() === 0) {
            $customFieldSetRepo->create([
                [
                    'name' => 'my_plugin_custom_fields',
                    'config' => [
                        'label' => [
                            'en-GB' => 'My Plugin Custom Fields',
                            'de-DE' => 'Meine Plugin Custom Fields'
                        ]
                    ],
                    'customFields' => [
                        [
                            'name' => 'attr21',
                            'type' => 'text',
                            'config' => [
                                'label' => ['en-GB' => 'Attribute 21'],
                            ]
                        ],
                        [
                            'name' => 'attr22',
                            'type' => 'text',
                            'config' => [
                                'label' => ['en-GB' => 'Attribute 22'],
                            ]
                        ],
                        // Add other fields here...
                    ],
                    'relations' => [
                        ['entityName' => 'product'],
                        // Add more entities if needed
                    ]
                ]
            ], Context::createDefaultContext());
        }
    }


    private function removeCustomFields(): void
    {
        $customFieldSetRepo = $this->container->get('custom_field_set.repository');

        // Find the ID of your set
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'my_plugin_custom_fields'));

        $ids = $customFieldSetRepo->searchIds($criteria, Context::createDefaultContext());

        foreach ($ids->getIds() as $id) {
            $customFieldSetRepo->delete([['id' => $id]], Context::createDefaultContext());
        }
    }

    private function updateDatabaseSchema(): void
    {
        $connection = $this->container->get(Connection::class);

        $queries = [
            "CREATE TABLE IF NOT EXISTS `car` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
        ];

        foreach ($queries as $query) {
            try {
                $connection->executeStatement($query);
            } catch (DBALException $e) {
                // Log error
            }
        }
    }

    private function dropDatabaseSchema(): void
    {
        $connection = $this->container->get(Connection::class);

        $queries = [
            "DROP TABLE IF EXISTS `car`;"
        ];

        foreach ($queries as $query) {
            try {
                $connection->executeStatement($query);
            } catch (DBALException $e) {
                // Log error
            }
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('services.xml');
    }
}

