<?php declare(strict_types=1);

namespace OmekaGraphQLExplorer;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;

/**
 * GraphiQL, in the Omeka admin, pointed at the GraphQL module's endpoint.
 *
 * It lives apart from that module on purpose. The endpoint is a few hundred
 * kilobytes of PHP that an installation may want to run without ever opening a
 * query editor; the editor is 1.6 MB of vendored JavaScript that has no reason
 * to sit in a production image serving machine clients. Splitting them lets an
 * operator install the endpoint and leave the editor out, which is the more
 * common of the two deployments.
 */
class Module extends AbstractModule
{
    public const VERSION = '0.1.0';

    /** The module this one is an editor for. */
    public const ENDPOINT_MODULE = 'OmekaGraphQL';

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Refuse to install without the endpoint.
     *
     * Omeka has no declarative dependency between modules, so the check has to
     * be made here. Failing at install is the kind moment to fail: the
     * alternative is an admin page that renders an editor against a route that
     * does not exist.
     */
    public function install(ServiceLocatorInterface $services)
    {
        $modules = $services->get('Omeka\ModuleManager');
        $endpoint = $modules->getModule(self::ENDPOINT_MODULE);

        if (!$endpoint || 'active' !== $endpoint->getState()) {
            throw new ModuleCannotInstallException(
                'The GraphQL module must be installed and active first: this module is an editor for its endpoint.' // @translate
            );
        }
    }
}
