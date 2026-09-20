<?php declare(strict_types=1);

namespace OmekaGraphQLExplorerTest\Fake;

use OmekaGraphQL\Schema\Definition\DataTypeKind;
use OmekaGraphQL\Schema\Definition\ExecutionLimits;
use OmekaGraphQL\Schema\Definition\FieldDefinition;
use OmekaGraphQL\Schema\Definition\SchemaDefinition;
use OmekaGraphQL\Schema\Definition\TypeDefinition;

/**
 * Stored schema definitions to write samples against.
 *
 * Built by hand rather than generated from templates: what the samples are
 * written from is the stored definition, so that is what the tests hand them.
 * The shapes here are chosen for what they deny a sample as much as for what
 * they offer it — a version with no media-backed type, one with nothing
 * substituted, one with no text anywhere — because leaving a sample out is
 * as much the factory's job as filling one in.
 */
final class Definitions
{
    /** Every shape a sample could ask for, in one version. */
    public static function complete(): SchemaDefinition
    {
        return new SchemaDefinition([
            new TypeDefinition('Photo', 1, 'Foto', [
                self::field('title', 10, DataTypeKind::STRING, ['literal'], [
                    'required' => true,
                    'substitute' => 'Zonder titel',
                    'langs' => ['nl', 'en', '*'],
                ]),
                self::field('description', 11, DataTypeKind::STRING, ['literal']),
                self::field('rights', 12, DataTypeKind::URI, ['uri']),
                self::field('width', 13, DataTypeKind::INT, ['literal']),
                self::field('creator', 14, DataTypeKind::RESOURCE, ['resource:item']),
            ], [TypeDefinition::RESOURCE_TYPE_ITEMS]),
            new TypeDefinition('Person', 2, 'Persoon', [
                self::field('name', 20, DataTypeKind::STRING, ['literal']),
                self::field('sameAs', 21, DataTypeKind::URI, ['uri']),
            ], [TypeDefinition::RESOURCE_TYPE_ITEMS]),
            new TypeDefinition('Scan', 3, 'Scan', [
                self::field('caption', 30, DataTypeKind::STRING, ['literal']),
            ], [TypeDefinition::RESOURCE_TYPE_MEDIA]),
            new TypeDefinition('Collection', 4, 'Collectie', [
                self::field('label', 40, DataTypeKind::STRING, ['literal']),
            ], [TypeDefinition::RESOURCE_TYPE_ITEM_SETS]),
        ], new ExecutionLimits());
    }

    /**
     * One version per shape a sample can be denied.
     *
     * @return array<string, SchemaDefinition>
     */
    public static function awkward(): array
    {
        return [
            // Served, generated, and holding nothing: a version whose
            // templates were all deleted still answers.
            'no types at all' => new SchemaDefinition([], new ExecutionLimits()),
            'items only' => new SchemaDefinition([
                new TypeDefinition('Doc', 1, 'Document', [
                    self::field('title', 10, DataTypeKind::STRING, ['literal']),
                ], [TypeDefinition::RESOURCE_TYPE_ITEMS]),
            ], new ExecutionLimits()),
            // Nothing to select but links: every sample that selects a scalar
            // has to cope with there being none.
            'links and nothing else' => new SchemaDefinition([
                new TypeDefinition('Node', 1, 'Knoop', [
                    self::field('related', 10, DataTypeKind::RESOURCE, ['resource']),
                ], [TypeDefinition::RESOURCE_TYPE_ITEMS]),
            ], new ExecutionLimits()),
            // No item-backed type, so no structural relation to traverse.
            'media only' => new SchemaDefinition([
                new TypeDefinition('Scan', 1, 'Scan', [
                    self::field('caption', 10, DataTypeKind::STRING, ['literal']),
                ], [TypeDefinition::RESOURCE_TYPE_MEDIA]),
            ], new ExecutionLimits()),
            // An item set whose items have no type in this version, and not a
            // string anywhere to ask a language of.
            'item sets, no text' => new SchemaDefinition([
                new TypeDefinition('Box', 1, 'Doos', [
                    self::field('count', 10, DataTypeKind::INT, ['literal']),
                ], [TypeDefinition::RESOURCE_TYPE_ITEM_SETS]),
            ], new ExecutionLimits()),
            // A template used by items and media alike, which the root list
            // field makes the caller choose between.
            'one template, two resource types' => new SchemaDefinition([
                new TypeDefinition('Mixed', 1, 'Gemengd', [
                    self::field('title', 10, DataTypeKind::STRING, ['literal']),
                ], [TypeDefinition::RESOURCE_TYPE_ITEMS, TypeDefinition::RESOURCE_TYPE_MEDIA]),
            ], new ExecutionLimits()),
            // Limits tighter than any sample would ask for on its own.
            'pages of two' => new SchemaDefinition([
                new TypeDefinition('Doc', 1, 'Document', [
                    self::field('title', 10, DataTypeKind::STRING, ['literal']),
                ], [TypeDefinition::RESOURCE_TYPE_ITEMS]),
            ], new ExecutionLimits(7, 5000, 2, 2)),
        ];
    }

    /**
     * @param list<string> $dataTypes
     * @param array<string, mixed> $options
     */
    private static function field(
        string $name,
        int $propertyId,
        string $kind,
        array $dataTypes,
        array $options = []
    ): FieldDefinition {
        return new FieldDefinition(
            $name,
            $propertyId,
            $options['term'] ?? 'dcterms:' . $name,
            $kind,
            $dataTypes,
            $options['required'] ?? false,
            $options['singular'] ?? false,
            $options['substitute'] ?? null,
            $options['langs'] ?? []
        );
    }
}
