<?php declare(strict_types=1);

namespace OmekaGraphQLExplorer\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use OmekaGraphQL\Omeka\ModuleSettings;
use OmekaGraphQL\Omeka\SchemaVersionRepository;
use OmekaGraphQL\Schema\SchemaService;
use OmekaGraphQLExplorer\Sample\SampleQueryFactory;

/**
 * GraphiQL, pointed at a served version of the endpoint.
 *
 * The client runs its queries against the real endpoint from the browser,
 * not through a server-side shortcut here: what an explorer is for is seeing
 * what a client sees, and that includes routing, version pinning and the
 * status code. It follows that results carry the signed-in identity — an
 * admin sees more here than an anonymous client will, which the page says out
 * loud rather than leaving to be discovered.
 */
class ExplorerController extends AbstractActionController
{
    public function __construct(
        private readonly SchemaService $schemaService,
        private readonly SchemaVersionRepository $versions,
        private readonly ModuleSettings $settings,
        private readonly SampleQueryFactory $samples = new SampleQueryFactory()
    ) {
    }

    public function indexAction()
    {
        $served = $this->versions->findServed();
        if ([] === $served) {
            $view = new ViewModel(['versions' => [], 'selected' => null, 'samples' => []]);
            return $view->setTemplate('graphql-explorer/admin/explorer/index');
        }

        $selected = $this->selectedVersion($served);
        $definition = $this->schemaService->definition($selected);
        // GraphiQL's documentation sidebar and its completions are both
        // introspection queries, so the endpoint setting decides whether
        // this page is an editor or a text box.
        $introspection = $this->settings->introspectionEnabled();

        $view = new ViewModel([
            'versions' => $served,
            'selected' => $selected,
            'stale' => $this->schemaService->isStale($selected),
            'limits' => $definition->limits,
            // Written against this version, because every name a sample could
            // use comes from this installation's templates.
            'samples' => $this->samples->forDefinition($definition, $introspection),
            'introspection' => $introspection,
        ]);
        return $view->setTemplate('graphql-explorer/admin/explorer/index');
    }

    /**
     * @param list<\OmekaGraphQL\Entity\SchemaVersion> $served
     */
    private function selectedVersion(array $served): \OmekaGraphQL\Entity\SchemaVersion
    {
        $requested = $this->params()->fromQuery('version');
        $selected = is_numeric($requested) ? $this->versions->findByVersion((int) $requested) : null;

        if ($selected && !$selected->isServed()) {
            // The endpoint 404s a pin on a version it no longer serves rather
            // than quietly answering with another one. A picker cannot 404, so
            // it says the same thing in the only way a page can.
            $this->messenger()->addWarning(sprintf(
                'Version %d is not served, so it has no endpoint to query. Showing the default version instead.', // @translate
                $selected->getVersion()
            ));
            $selected = null;
        }

        return $selected ?? $this->versions->findDefault() ?? $served[0];
    }
}
