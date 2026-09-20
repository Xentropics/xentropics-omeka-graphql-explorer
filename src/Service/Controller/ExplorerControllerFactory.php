<?php declare(strict_types=1);

namespace OmekaGraphQLExplorer\Service\Controller;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OmekaGraphQL\Omeka\ModuleSettings;
use OmekaGraphQL\Omeka\SchemaVersionRepository;
use OmekaGraphQL\Schema\SchemaService;
use OmekaGraphQLExplorer\Controller\Admin\ExplorerController;

class ExplorerControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null): ExplorerController
    {
        // Services owned by the endpoint module. This module never touches its
        // entities or its schema builder directly; it asks the same services
        // the admin pages there ask.
        return new ExplorerController(
            $services->get(SchemaService::class),
            $services->get(SchemaVersionRepository::class),
            $services->get(ModuleSettings::class)
        );
    }
}
