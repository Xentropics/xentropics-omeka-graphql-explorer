<?php declare(strict_types=1);

namespace OmekaGraphQLExplorer\Sample;

use OmekaGraphQL\Schema\Build\SchemaBuilder;
use OmekaGraphQL\Schema\Definition\DataTypeKind;
use OmekaGraphQL\Schema\Definition\FieldDefinition;
use OmekaGraphQL\Schema\Definition\SchemaDefinition;
use OmekaGraphQL\Schema\Definition\TypeDefinition;
use OmekaGraphQL\Schema\Generator\NameFactory;

/**
 * Example queries, written against the version being explored.
 *
 * Generic examples would be worthless here. Every name a client can write —
 * the types, their fields, the root queries — is derived from this
 * installation's resource templates, so an example naming `Item` or `title`
 * is an example that does not run. These are built from the same definition
 * the endpoint resolves against, which makes them runnable as written.
 *
 * Each one carries a comment saying what it demonstrates, because the point
 * of a sample here is the shape of the answer — pagination, the Resource
 * interface, the language chain — rather than the fields it happens to pick.
 *
 * Samples are self-contained: where one wants a variable it declares one with
 * a default, so it runs straight from the editor without anyone filling in
 * the variables pane first.
 */
final class SampleQueryFactory
{
    /** Fields to select on a list sample before it stops being readable. */
    private const FIELDS_SHOWN = 4;

    /**
     * @param list<int> $unusedTemplateIds Templates the generator saw no
     *     resource using. A sample built on one of those runs correctly and
     *     answers `totalCount: 0`, which teaches nothing about the collection
     *     and reads as a broken example. Omeka ships a "Base Resource"
     *     template that most installations never assign to anything and that
     *     carries more properties than any real template, so without this it
     *     wins on field count and every sample is written against the one type
     *     holding no data.
     */
    public function __construct(
        private readonly NameFactory $names = new NameFactory(),
        private readonly array $unusedTemplateIds = []
    ) {
    }

    /**
     * @param bool $introspection Whether the endpoint answers introspection.
     *     A sample that cannot run is worse than one fewer sample.
     * @return list<SampleQuery>
     */
    public function forDefinition(SchemaDefinition $definition, bool $introspection = true): array
    {
        $type = $this->primaryType($definition);

        $samples = [$this->version()];
        if (null !== $type) {
            $samples[] = $this->list($definition, $type);
            $samples[] = $this->search($definition, $type);
            $samples[] = $this->byId($definition);
            $samples[] = $this->language($definition, $type);
            $samples[] = $this->links($definition);
            $samples[] = $this->media($definition);
            $samples[] = $this->itemSetContents($definition);
            $samples[] = $this->substituted($definition);
        }
        if ($introspection) {
            $samples[] = $this->introspection();
        }

        return array_values(array_filter($samples));
    }

    private function version(): SampleQuery
    {
        return new SampleQuery(
            'Which version am I talking to?', // @translate
            'The one field every version answers, whatever templates it was generated from. Worth running first when a client is not getting what it expects.', // @translate
            <<<'GRAPHQL'
                # The version this endpoint is serving. A client that pins a version
                # can assert on this; one that does not can discover it.
                {
                  graphqlSchemaVersion
                }
                GRAPHQL
        );
    }

    private function list(SchemaDefinition $definition, TypeDefinition $type): SampleQuery
    {
        $limit = $this->pageSize($definition, 5);
        $selection = $this->selections($type, self::FIELDS_SHOWN);

        return new SampleQuery(
            sprintf('A page of %s', $type->name), // @translate
            sprintf(
                'Listing, pagination and the fields generated from the %s template.', // @translate
                $type->templateLabel
            ),
            $this->render([
                '# `totalCount` is what your identity may see, not how many rows exist:',
                '# it is counted after filtering, so the pages add up to it.',
                '{',
                '  ' . $this->names->queryListField($type->name) . '(limit: ' . $limit . ') {',
                '    totalCount',
                '    pageInfo { hasNextPage limit offset }',
                '    nodes {',
                '      id',
                ...$this->indent($selection, 6),
                '    }',
                '  }',
                '}',
            ])
        );
    }

    private function search(SchemaDefinition $definition, TypeDefinition $type): SampleQuery
    {
        $limit = $this->pageSize($definition, 5);
        $selection = $this->indent($this->selections($type, 1), 6);

        return new SampleQuery(
            'Search for a word', // @translate
            "Omeka's own full-text search, reached through a declared variable. Put a term in the default and run it again.", // @translate
            $this->render([
                '# `search` runs the same full-text index the admin search box does, so',
                '# it matches on values this query never selects. An empty term is no',
                '# filter at all, which is why this one runs before you have typed in it.',
                'query Search($term: String = "") {',
                '  ' . $this->names->queryListField($type->name) . '(search: $term, limit: ' . $limit . ') {',
                '    totalCount',
                '    nodes {',
                '      id',
                ...$selection,
                '    }',
                '  }',
                '}',
            ])
        );
    }

    private function byId(SchemaDefinition $definition): SampleQuery
    {
        $fragments = [];
        foreach (array_slice($this->rankedTypes($definition), 0, 2) as $type) {
            $selection = $this->selections($type, 2);
            if ([] === $selection) {
                continue;
            }
            $fragments[] = '    ... on ' . $type->name . ' {';
            $fragments = array_merge($fragments, $this->indent($selection, 6));
            $fragments[] = '    }';
        }

        return new SampleQuery(
            'One resource, whatever its template', // @translate
            'The interface every resource implements, and the inline fragments a client needs to read past it. Swap in an id from one of the list samples.', // @translate
            $this->render(array_merge([
                '# `resourceById` reads any resource in this version. What comes back is',
                '# the Resource interface — the fields every resource has — so naming a',
                '# generated type with an inline fragment is how you reach the rest.',
                'query Resource($id: ID = "1") {',
                '  resourceById(id: $id) {',
                '    id',
                '    resourceType',
                '    isPublic',
                '    created',
            ], $fragments, [
                '  }',
                '}',
            ]))
        );
    }

    private function language(SchemaDefinition $definition, TypeDefinition $type): ?SampleQuery
    {
        $field = $this->firstField($type, static fn (FieldDefinition $f): bool => DataTypeKind::STRING === $f->kind);
        if (null === $field) {
            return null;
        }

        $chain = $field->defaultLangs ?: ['en', '*'];
        $asked = implode(', ', array_map(static fn (string $l): string => '"' . $l . '"', $chain));

        return new SampleQuery(
            'Ask for a language', // @translate
            'How the language chain resolves, and why the tag that comes back is worth reading.', // @translate
            $this->render([
                '# The chain is tried in order: "*" matches any language, "" matches a',
                '# value stored without one. A language nothing is catalogued in falls',
                '# through to the next entry rather than answering with nothing, so the',
                '# `language` beside the value is what tells you which one you got.',
                '{',
                '  ' . $this->names->queryListField($type->name) . '(limit: ' . $this->pageSize($definition, 3) . ') {',
                '    nodes {',
                '      id',
                '      ' . $field->name . '(lang: [' . $asked . ']) { value language }',
                '    }',
                '  }',
                '}',
            ])
        );
    }

    private function links(SchemaDefinition $definition): ?SampleQuery
    {
        foreach ($this->rankedTypes($definition) as $type) {
            $field = $this->firstField($type, static fn (FieldDefinition $f): bool => $f->isResource());
            if (null === $field) {
                continue;
            }

            $target = $this->targetType($definition, $field, $type);
            $inner = null === $target
                ? ['          resourceType']
                : $this->indent(array_merge(
                    ['... on ' . $target->name . ' {'],
                    $this->indent($this->selections($target, 1), 2),
                    ['}']
                ), 10);

            return new SampleQuery(
                'Follow a link', // @translate
                sprintf(
                    'Traversing %s.%s, and why a linked resource has to be named before it can be read.', // @translate
                    $type->name,
                    $field->name
                ),
                $this->render(array_merge([
                    '# A template records that a value points at a resource, never which',
                    '# generated type that resource has, so links are the Resource interface',
                    '# too. Spelling out each level is also what keeps cycles tractable:',
                    '# two resources pointing at each other is all it takes to make one.',
                    '{',
                    '  ' . $this->names->queryListField($type->name) . '(limit: ' . $this->pageSize($definition, 3) . ') {',
                    '    nodes {',
                    '      id',
                    '      ' . $field->name . '(limit: 3) {',
                    '        totalCount',
                    '        nodes {',
                    '          id',
                ], $inner, [
                    '        }',
                    '      }',
                    '    }',
                    '  }',
                    '}',
                ]))
            );
        }

        return null;
    }

    private function media(SchemaDefinition $definition): ?SampleQuery
    {
        $item = $this->firstType($definition, TypeDefinition::RESOURCE_TYPE_ITEMS);
        $media = $this->firstType($definition, TypeDefinition::RESOURCE_TYPE_MEDIA);
        if (null === $item || null === $media) {
            // Media whose template is not in this version are filtered out, so
            // without a media-backed type here the connection is always empty
            // and the sample would teach the wrong lesson.
            return null;
        }

        return new SampleQuery(
            'An item and its files', // @translate
            sprintf('Media hanging off %s, read as the %s they were generated as.', $item->name, $media->name), // @translate
            $this->render([
                '# Media are a forward traversal of media.item_id, not an inverse relation,',
                '# so the page is bounded by the item. Media whose template is not in this',
                '# version are left out: there is no type to resolve them to.',
                '{',
                '  ' . $this->names->queryListField($item->name) . '(limit: ' . $this->pageSize($definition, 3) . ') {',
                '    nodes {',
                '      id',
                '      media(limit: 5) {',
                '        totalCount',
                '        nodes {',
                '          id',
                '          ... on ' . $media->name . ' {',
                '            mediaType',
                '            originalUrl',
                '          }',
                '        }',
                '      }',
                '    }',
                '  }',
                '}',
            ])
        );
    }

    private function itemSetContents(SchemaDefinition $definition): ?SampleQuery
    {
        $set = $this->firstType($definition, TypeDefinition::RESOURCE_TYPE_ITEM_SETS);
        if (null === $set) {
            return null;
        }
        $item = $this->firstType($definition, TypeDefinition::RESOURCE_TYPE_ITEMS);
        $inner = null === $item
            ? ['          resourceType']
            : $this->indent(array_merge(
                ['... on ' . $item->name . ' {'],
                $this->indent($this->selections($item, 1), 2),
                ['}']
            ), 10);

        return new SampleQuery(
            'What is in an item set', // @translate
            sprintf('Walking from %s to its items through the join table.', $set->name), // @translate
            $this->render(array_merge([
                '# The same shape as media: a bounded page of what this set holds, limited',
                '# to items whose template this version knows.',
                '{',
                '  ' . $this->names->queryListField($set->name) . '(limit: 3) {',
                '    nodes {',
                '      id',
                '      items(limit: 5) {',
                '        totalCount',
                '        pageInfo { hasNextPage }',
                '        nodes {',
                '          id',
            ], $inner, [
                '        }',
                '      }',
                '    }',
                '  }',
                '}',
            ]))
        );
    }

    private function substituted(SchemaDefinition $definition): ?SampleQuery
    {
        foreach ($this->rankedTypes($definition) as $type) {
            $fields = $type->substitutedFields();
            if ([] === $fields) {
                continue;
            }
            $field = $fields[0];

            return new SampleQuery(
                'Which values were filled in', // @translate
                sprintf(
                    'Telling a supplied value from a catalogued one. On %s, %s carries a configured default.', // @translate
                    $type->name,
                    $field->name
                ),
                $this->render([
                    '# `substituted` names the fields on this node answering with a configured',
                    '# default because nothing was catalogued. It is machine-readable on',
                    '# purpose: that difference has to survive into exports and downstream',
                    '# RDF, where a supplied value that reads as catalogued is a lie.',
                    '{',
                    '  ' . $this->names->queryListField($type->name) . '(limit: ' . $this->pageSize($definition, 10) . ') {',
                    '    nodes {',
                    '      id',
                    '      ' . $field->name . ($this->needsSubselection($field) ? ' { value }' : ''),
                    '      substituted',
                    '    }',
                    '  }',
                    '}',
                ])
            );
        }

        return null;
    }

    private function introspection(): SampleQuery
    {
        return new SampleQuery(
            'Every root field', // @translate
            'The question the Docs sidebar asks, written out. Useful when you want the list as data rather than as a panel.', // @translate
            <<<'GRAPHQL'
                # Introspection is what the documentation sidebar and the completions run
                # on. Disabling it in the module configuration closes both, and this
                # query with them, while ordinary queries keep working.
                {
                  __schema {
                    queryType {
                      fields {
                        name
                        description
                      }
                    }
                  }
                }
                GRAPHQL
        );
    }

    /**
     * Selection lines for a type's own fields, scalars first.
     *
     * Resource-valued fields are left out: they are connections, and a
     * connection nested in every sample would bury what each one is for.
     *
     * @return list<string>
     */
    private function selections(TypeDefinition $type, int $max): array
    {
        $lines = [];
        foreach ($type->fields as $field) {
            if ($field->isResource() || count($lines) >= $max) {
                continue;
            }
            $lines[] = match ($field->kind) {
                DataTypeKind::STRING => $field->name . ' { value }',
                DataTypeKind::URI => $field->name . ' { uri label }',
                default => $field->name,
            };
        }
        return $lines;
    }

    private function needsSubselection(FieldDefinition $field): bool
    {
        return in_array($field->kind, [DataTypeKind::STRING, DataTypeKind::URI], true);
    }

    /**
     * The type a sample should lead with.
     *
     * Items before anything else — an installation has most of them, and the
     * structural relations hang off them — then whichever carries the most
     * fields, then by name so the list is the same on every page load.
     */
    private function primaryType(SchemaDefinition $definition): ?TypeDefinition
    {
        return $this->rankedTypes($definition)[0] ?? null;
    }

    /** @return list<TypeDefinition> */
    private function rankedTypes(SchemaDefinition $definition): array
    {
        $unused = $this->unusedTemplateIds;
        $types = $definition->types;
        usort($types, static function (TypeDefinition $a, TypeDefinition $b) use ($unused): int {
            // A type with nothing behind it goes last whatever else it has
            // going for it: a sample that lists an empty collection is a
            // sample that demonstrates nothing.
            $used = (int) !in_array($b->templateId, $unused, true)
                <=> (int) !in_array($a->templateId, $unused, true);
            if (0 !== $used) {
                return $used;
            }
            $items = (int) $b->hasResourceType(TypeDefinition::RESOURCE_TYPE_ITEMS)
                <=> (int) $a->hasResourceType(TypeDefinition::RESOURCE_TYPE_ITEMS);
            if (0 !== $items) {
                return $items;
            }
            return count($b->fields) <=> count($a->fields) ?: strcmp($a->name, $b->name);
        });
        return $types;
    }

    private function firstType(SchemaDefinition $definition, string $resourceType): ?TypeDefinition
    {
        foreach ($this->rankedTypes($definition) as $type) {
            if ($type->hasResourceType($resourceType)) {
                return $type;
            }
        }
        return null;
    }

    /** @param callable(FieldDefinition): bool $predicate */
    private function firstField(TypeDefinition $type, callable $predicate): ?FieldDefinition
    {
        foreach ($type->fields as $field) {
            if ($predicate($field)) {
                return $field;
            }
        }
        return null;
    }

    /**
     * A type the link in $field could resolve to.
     *
     * The field declares which Omeka resource types it accepts, never which
     * generated type, so this is a plausible fragment rather than the only
     * one — which is the lesson the sample is there to teach.
     *
     * $from is the type doing the linking, and is the last candidate rather
     * than the first: a link from a type to itself is legal and this cannot
     * rule it out, but a sample that reads `Photo` inside `Photo` looks like
     * a mistake even when it is not one.
     */
    private function targetType(
        SchemaDefinition $definition,
        FieldDefinition $field,
        ?TypeDefinition $from = null
    ): ?TypeDefinition {
        $resourceTypes = SchemaBuilder::resourceTypesForDataTypes($field->dataTypes);
        $fallback = null;

        foreach ($this->rankedTypes($definition) as $type) {
            foreach ($resourceTypes as $resourceType) {
                if (!$type->hasResourceType($resourceType) || [] === $this->selections($type, 1)) {
                    continue;
                }
                if (null !== $from && $type->name === $from->name) {
                    $fallback = $type;
                    continue;
                }
                return $type;
            }
        }

        return $fallback;
    }

    /** A sample page size that the version's own limits would not clamp. */
    private function pageSize(SchemaDefinition $definition, int $wanted): int
    {
        return $definition->limits->clampPageSize($wanted);
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function indent(array $lines, int $spaces): array
    {
        $pad = str_repeat(' ', $spaces);
        return array_map(static fn (string $line): string => $pad . $line, $lines);
    }

    /** @param list<string> $lines */
    private function render(array $lines): string
    {
        return implode("\n", $lines) . "\n";
    }
}
