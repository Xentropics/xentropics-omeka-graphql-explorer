<?php declare(strict_types=1);

namespace OmekaGraphQLExplorerTest\Sample;

use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use OmekaGraphQL\Schema\Build\SchemaBuilder;
use OmekaGraphQL\Schema\Definition\SchemaDefinition;
use OmekaGraphQLExplorer\Sample\SampleQuery;
use OmekaGraphQLExplorer\Sample\SampleQueryFactory;
use OmekaGraphQLExplorerTest\Fake\Definitions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The samples, checked against the schema they claim to be written for.
 *
 * This is the suite's reason to exist. A sample is offered as runnable, and
 * the only thing that makes it runnable is agreeing with what
 * `SchemaBuilder` produces from the same definition — a field name, an
 * argument, a sub-selection a scalar does or does not need. None of that is
 * visible from inside this module, which is exactly why it is asserted here:
 * the endpoint can change the shape of its schema without this module seeing
 * a thing, and a sample that has quietly stopped parsing is worse than no
 * sample, because the page still offers it.
 */
final class SampleQueryFactoryTest extends TestCase
{
    private SampleQueryFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SampleQueryFactory();
    }

    public function testEverySampleRunsAgainstTheVersionItWasWrittenFor(): void
    {
        $definition = Definitions::complete();
        $samples = $this->factory->forDefinition($definition);

        self::assertNotEmpty($samples);
        $this->assertAllValid($samples, $definition);
    }

    /**
     * @param SchemaDefinition $definition
     */
    #[DataProvider('awkwardVersions')]
    public function testEverySampleRunsWhateverShapeTheVersionIs(SchemaDefinition $definition): void
    {
        $samples = $this->factory->forDefinition($definition);

        // Whatever a version holds, it answers `graphqlSchemaVersion`, so
        // there is always something to offer.
        self::assertNotEmpty($samples);
        $this->assertAllValid($samples, $definition);
    }

    /** @return array<string, array{SchemaDefinition}> */
    public static function awkwardVersions(): array
    {
        return array_map(
            static fn (SchemaDefinition $definition): array => [$definition],
            Definitions::awkward()
        );
    }

    public function testSamplesNameTheTypesThisVersionGenerated(): void
    {
        $queries = $this->queries($this->factory->forDefinition(Definitions::complete()));

        // The root list field is derived from the type name by the endpoint's
        // own NameFactory. If that derivation changes, these stop resolving.
        self::assertStringContainsString('photoList(', $queries);
        self::assertStringContainsString('... on Person', $queries);
        self::assertStringContainsString('... on Scan', $queries);
        self::assertStringContainsString('collectionList(', $queries);
    }

    public function testPageSizesStayWithinTheVersionsOwnLimits(): void
    {
        $definition = Definitions::awkward()['pages of two'];
        $maximum = $definition->limits->maxPageSize;

        preg_match_all('/limit: (\d+)/', $this->queries($this->factory->forDefinition($definition)), $matches);

        self::assertNotEmpty($matches[1], 'The samples should ask for pages at all.');
        foreach ($matches[1] as $limit) {
            self::assertLessThanOrEqual(
                $maximum,
                (int) $limit,
                'A sample asked for a page the endpoint would clamp, which makes the sample wrong about its own schema.'
            );
        }
    }

    public function testIntrospectionSampleIsLeftOutWhenTheEndpointWillNotAnswerIt(): void
    {
        $definition = Definitions::complete();

        $offered = $this->titles($this->factory->forDefinition($definition, true));
        $withheld = $this->titles($this->factory->forDefinition($definition, false));

        self::assertContains('Every root field', $offered);
        self::assertNotContains('Every root field', $withheld);
        // Only that one goes: disabling introspection closes the sidebar, not
        // the endpoint.
        self::assertSame([], array_diff($withheld, $offered));
        self::assertCount(count($offered) - 1, $withheld);
    }

    public function testASampleIsLeftOutRatherThanShownAgainstAVersionThatCannotAnswerIt(): void
    {
        $complete = $this->titles($this->factory->forDefinition(Definitions::complete()));
        self::assertContains('An item and its files', $complete);
        self::assertContains('What is in an item set', $complete);
        self::assertContains('Which values were filled in', $complete);
        self::assertContains('Ask for a language', $complete);

        // No media-backed type: the connection would always be empty, and an
        // empty sample teaches the wrong lesson about why.
        $itemsOnly = $this->titles($this->factory->forDefinition(Definitions::awkward()['items only']));
        self::assertNotContains('An item and its files', $itemsOnly);
        self::assertNotContains('What is in an item set', $itemsOnly);
        // Nothing is substituted here, so there is no difference to show.
        self::assertNotContains('Which values were filled in', $itemsOnly);

        // Not a text value in the version, so no language chain to ask about.
        $noText = $this->titles($this->factory->forDefinition(Definitions::awkward()['item sets, no text']));
        self::assertNotContains('Ask for a language', $noText);
    }

    public function testAVersionWithNoTypesStillHasSomethingToRun(): void
    {
        $samples = $this->factory->forDefinition(Definitions::awkward()['no types at all'], false);

        self::assertSame(['Which version am I talking to?'], $this->titles($samples));
    }

    /**
     * @param list<SampleQuery> $samples
     */
    private function assertAllValid(array $samples, SchemaDefinition $definition): void
    {
        $schema = $this->schema($definition);

        foreach ($samples as $sample) {
            $document = Parser::parse($sample->query);
            $errors = DocumentValidator::validate($schema, $document);

            self::assertSame(
                [],
                array_map(static fn ($error): string => $error->getMessage(), $errors),
                sprintf('The sample "%s" does not validate against the schema it was written for.', $sample->title)
            );
        }
    }

    private function schema(SchemaDefinition $definition): Schema
    {
        $schema = (new SchemaBuilder($definition))->build(1);
        // A schema this module built a query for has to be a legal one first,
        // or an invalid sample could pass for a valid one.
        $schema->assertValid();
        return $schema;
    }

    /**
     * @param list<SampleQuery> $samples
     * @return list<string>
     */
    private function titles(array $samples): array
    {
        return array_map(static fn (SampleQuery $sample): string => $sample->title, $samples);
    }

    /**
     * @param list<SampleQuery> $samples
     */
    private function queries(array $samples): string
    {
        return implode("\n", array_map(static fn (SampleQuery $sample): string => $sample->query, $samples));
    }
}
