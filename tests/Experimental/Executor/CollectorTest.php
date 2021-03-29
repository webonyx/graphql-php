<?php

declare(strict_types=1);

namespace GraphQL\Tests\Experimental\Executor;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\FormattedError;
use GraphQL\Experimental\Executor\Collector;
use GraphQL\Experimental\Executor\Runtime;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\Parser;
use GraphQL\Tests\StarWarsSchema;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

use function array_map;
use function basename;
use function count;
use function file_exists;
use function file_put_contents;
use function json_encode;
use function strlen;
use function strncmp;

use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class CollectorTest extends TestCase
{
    /**
     * @param mixed[]|null $variableValues
     *
     * @dataProvider provideForTestCollectFields
     */
    public function testCollectFields(Schema $schema, DocumentNode $documentNode, string $operationName, ?array $variableValues)
    {
        $runtime = new class ($variableValues) implements Runtime
        {
            /** @var Throwable[] */
            public $errors = [];

            /** @var mixed[]|null */
            public $variableValues;

            public function __construct($variableValues)
            {
                $this->variableValues = $variableValues;
            }

            public function evaluate(ValueNode $valueNode, InputType $type)
            {
                return AST::valueFromAST($valueNode, $type, $this->variableValues);
            }

            public function addError($error)
            {
                $this->errors[] = $error;
            }
        };

        $collector = new Collector($schema, $runtime);
        $collector->initialize($documentNode, $operationName);

        $pipeline = [];
        foreach ($collector->collectFields($collector->rootType, $collector->operation->selectionSet) as $shared) {
            $execution = new stdClass();
            if (count($shared->fieldNodes ?? []) > 0) {
                $execution->fieldNodes = array_map(static function (Node $node): array {
                    return $node->toArray(true);
                }, $shared->fieldNodes);
            }

            if (strlen($shared->fieldName ?? '') > 0) {
                $execution->fieldName = $shared->fieldName;
            }

            if (strlen($shared->resultName ?? '') > 0) {
                $execution->resultName = $shared->resultName;
            }

            if (isset($shared->argumentValueMap)) {
                $execution->argumentValueMap = [];
                /** @var Node $valueNode */
                foreach ($shared->argumentValueMap as $argumentName => $valueNode) {
                    $execution->argumentValueMap[$argumentName] = $valueNode->toArray(true);
                }
            }

            $pipeline[] = $execution;
        }

        if (strncmp($operationName, 'ShouldEmitError', strlen('ShouldEmitError')) === 0) {
            self::assertNotEmpty($runtime->errors, 'There should be errors.');
        } else {
            self::assertEmpty($runtime->errors, 'There must be no errors. Got: ' . json_encode($runtime->errors, JSON_PRETTY_PRINT));

            if (strncmp($operationName, 'ShouldNotEmit', strlen('ShouldNotEmit')) === 0) {
                self::assertEmpty($pipeline, 'No instructions should be emitted.');
            } else {
                self::assertNotEmpty($pipeline, 'There should be some instructions emitted.');
            }
        }

        $result = [];
        if (count($runtime->errors) > 0) {
            $result['errors'] = array_map(
                FormattedError::prepareFormatter(null, DebugFlag::NONE),
                $runtime->errors
            );
        }

        if (count($pipeline) > 0) {
            $result['pipeline'] = $pipeline;
        }

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        $fileName = __DIR__ . DIRECTORY_SEPARATOR . basename(__FILE__, '.php') . 'Snapshots' . DIRECTORY_SEPARATOR . $operationName . '.json';
        if (! file_exists($fileName)) {
            file_put_contents($fileName, $json);
        }

        self::assertStringEqualsFile($fileName, $json);
    }

    public function provideForTestCollectFields()
    {
        $testCases = [
            [
                StarWarsSchema::build(),
                'query ShouldEmitFieldWithoutArguments {
                    human {
                        name                    
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitFieldThatHasArguments($id: ID!) {
                    human(id: $id) {
                        name
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitForInlineFragment($id: ID!) {
                    ...HumanById
                }
                fragment HumanById on Query {
                    human(id: $id) {
                        ... on Human {
                            name
                        }
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitObjectFieldForFragmentSpread($id: ID!) {
                    human(id: $id) {
                        ...HumanName
                    }
                }
                fragment HumanName on Human {
                    name
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitTypeName {
                    queryTypeName: __typename
                    __typename
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitIfIncludeConditionTrue($id: ID!, $condition: Boolean!) {
                    droid(id: $id) @include(if: $condition) {
                        id
                    }
                }',
                ['condition' => true],
            ],
            [
                StarWarsSchema::build(),
                'query ShouldNotEmitIfIncludeConditionFalse($id: ID!, $condition: Boolean!) {
                    droid(id: $id) @include(if: $condition) {
                        id
                    }
                }',
                ['condition' => false],
            ],
            [
                StarWarsSchema::build(),
                'query ShouldNotEmitIfSkipConditionTrue($id: ID!, $condition: Boolean!) {
                    droid(id: $id) @skip(if: $condition) {
                        id
                    }
                }',
                ['condition' => true],
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitIfSkipConditionFalse($id: ID!, $condition: Boolean!) {
                    droid(id: $id) @skip(if: $condition) {
                        id
                    }
                }',
                ['condition' => false],
            ],
            [
                StarWarsSchema::build(),
                'query ShouldNotEmitIncludeSkipTT($id: ID!, $includeCondition: Boolean!, $skipCondition: Boolean!) {
                    droid(id: $id) @include(if: $includeCondition) @skip(if: $skipCondition) {
                        id
                    }
                }',
                ['includeCondition' => true, 'skipCondition' => true],
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitIncludeSkipTF($id: ID!, $includeCondition: Boolean!, $skipCondition: Boolean!) {
                    droid(id: $id) @include(if: $includeCondition) @skip(if: $skipCondition) {
                        id
                    }
                }',
                ['includeCondition' => true, 'skipCondition' => false],
            ],
            [
                StarWarsSchema::build(),
                'query ShouldNotEmitIncludeSkipFT($id: ID!, $includeCondition: Boolean!, $skipCondition: Boolean!) {
                    droid(id: $id) @include(if: $includeCondition) @skip(if: $skipCondition) {
                        id
                    }
                }',
                ['includeCondition' => false, 'skipCondition' => true],
            ],
            [
                StarWarsSchema::build(),
                'query ShouldNotEmitIncludeSkipFF($id: ID!, $includeCondition: Boolean!, $skipCondition: Boolean!) {
                    droid(id: $id) @include(if: $includeCondition) @skip(if: $skipCondition) {
                        id
                    }
                }',
                ['includeCondition' => false, 'skipCondition' => false],
            ],
            [
                StarWarsSchema::build(),
                'query ShouldNotEmitSkipAroundInlineFragment {
                    ... on Query @skip(if: true) {
                        hero(episode: 5) {
                            name
                        }
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitSkipAroundInlineFragment {
                    ... on Query @skip(if: false) {
                        hero(episode: 5) {
                            name
                        }
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitIncludeAroundInlineFragment {
                    ... on Query @include(if: true) {
                        hero(episode: 5) {
                            name
                        }
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldNotEmitIncludeAroundInlineFragment {
                    ... on Query @include(if: false) {
                        hero(episode: 5) {
                            name
                        }
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldNotEmitSkipFragmentSpread {
                    ...Hero @skip(if: true)
                }
                fragment Hero on Query {
                    hero(episode: 5) {
                        name
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitSkipFragmentSpread {
                    ...Hero @skip(if: false)
                }
                fragment Hero on Query {
                    hero(episode: 5) {
                        name
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitIncludeFragmentSpread {
                    ...Hero @include(if: true)
                }
                fragment Hero on Query {
                    hero(episode: 5) {
                        name
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldNotEmitIncludeFragmentSpread {
                    ...Hero @include(if: false)
                }
                fragment Hero on Query {
                    hero(episode: 5) {
                        name
                    }
                }',
                null,
            ],
            [
                StarWarsSchema::build(),
                'query ShouldEmitSingleInstrictionForSameResultName($id: ID!) {
                    human(id: $id) {
                        name
                        name: secretBackstory 
                    }
                }',
                null,
            ],
        ];

        $data = [];
        foreach ($testCases as [$schema, $query, $variableValues]) {
            $documentNode  = Parser::parse($query, ['noLocation' => true]);
            $operationName = null;
            /** @var Node $definitionNode */
            foreach ($documentNode->definitions as $definitionNode) {
                if ($definitionNode instanceof OperationDefinitionNode) {
                    self::assertNotNull($definitionNode->name);
                    $operationName = $definitionNode->name->value;
                    break;
                }
            }

            self::assertArrayNotHasKey($operationName, $data);

            $data[$operationName] = [$schema, $documentNode, $operationName, $variableValues];
        }

        return $data;
    }
}
