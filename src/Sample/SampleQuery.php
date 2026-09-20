<?php declare(strict_types=1);

namespace OmekaGraphQLExplorer\Sample;

/**
 * One runnable example, written against a particular schema version.
 *
 * The description is what the sample teaches, not what it selects: the query
 * is right there to be read. It is prose for the page, so it is escaped where
 * it is rendered rather than here.
 */
final class SampleQuery
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $query
    ) {
    }
}
