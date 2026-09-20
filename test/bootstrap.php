<?php declare(strict_types=1);

/**
 * The explorer's tests, and the endpoint module they are written against.
 *
 * There is no Composer dependency on that module and there should not be: it
 * is not on Packagist, Omeka resolves modules at runtime from its own
 * `modules/` directory, and a path repository pointing at a sibling checkout
 * would encode one developer's layout into the manifest. So it is found the
 * way Omeka finds it — next door — and looked for under both names it goes by:
 * `OmekaGraphQL` where Omeka requires the directory to carry the namespace,
 * and the repository name where the two are checked out side by side.
 * `OMEKA_GRAPHQL_PATH` overrides both.
 *
 * Its autoloader is what is required here, not its sources: that is what
 * carries graphql-php, which the samples are validated with.
 *
 * Not finding it is a failure rather than a skip. A suite whose one job is to
 * prove the samples still fit the endpoint's schema has nothing to report if
 * it cannot see the endpoint, and reporting that as green is worse than
 * reporting nothing.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

// Set deliberately, so it is the answer rather than the first guess: falling
// through from a path someone typed would run the suite against a different
// module than the one they named, and pass.
$configured = getenv('OMEKA_GRAPHQL_PATH');

$candidates = false !== $configured && '' !== $configured
    ? [$configured]
    : [
        dirname(__DIR__, 2) . '/OmekaGraphQL',
        dirname(__DIR__, 2) . '/xentropics-omeka-graphql',
    ];

foreach ($candidates as $candidate) {
    $autoload = rtrim($candidate, '/') . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require $autoload;
        return;
    }
}

throw new RuntimeException(sprintf(
    "The GraphQL endpoint module was not found, so its schema cannot be built and the samples "
    . "cannot be checked against it.\nLooked for vendor/autoload.php under:\n  - %s\n"
    . "Set OMEKA_GRAPHQL_PATH to the module's directory, and run `composer install` in it.",
    implode("\n  - ", $candidates)
));
