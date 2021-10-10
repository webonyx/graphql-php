<?php

declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;

use function array_map;

/**
 * Build instance of @see \GraphQL\Type\Schema out of schema language definition (string or parsed AST).
 *
 * See [schema definition language docs](schema-definition-language.md) for details.
 *
 * @phpstan-type Options array{
 *   commentDescriptions?: bool,
 * }
 *
 *    - commentDescriptions:
 *        Provide true to use preceding comments as the description.
 *        This option is provided to ease adoption and will be removed in v16.
 */
class BuildSchema
{
    private DocumentNode $ast;

    /** @var array<string, TypeDefinitionNode> */
    private array $nodeMap;

    /** @var callable|null */
    private $typeConfigDecorator;

    /**
     * @var array<string, bool>
     * @phpstan-var Options
     */
    private array $options;

    /**
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     */
    public function __construct(
        DocumentNode $ast,
        ?callable $typeConfigDecorator = null,
        array $options = []
    ) {
        $this->ast                 = $ast;
        $this->typeConfigDecorator = $typeConfigDecorator;
        $this->options             = $options;
    }

    /**
     * A helper function to build a GraphQLSchema directly from a source
     * document.
     *
     * @param DocumentNode|Source|string $source
     * @param array<string, bool>        $options
     * @phpstan-param Options $options
     *
     * @api
     */
    public static function build(
        $source,
        ?callable $typeConfigDecorator = null,
        array $options = []
    ): Schema {
        $doc = $source instanceof DocumentNode
            ? $source
            : Parser::parse($source);

        return self::buildAST($doc, $typeConfigDecorator, $options);
    }

    /**
     * This takes the AST of a schema from @see \GraphQL\Language\Parser::parse().
     *
     * If no schema definition is provided, then it will look for types named Query and Mutation.
     *
     * Given that AST it constructs a @see \GraphQL\Type\Schema. The resulting schema
     * has no resolve methods, so execution will use default resolvers.
     *
     * @param array<string, bool> $options
     * @phpstan-param Options $options
     *
     * @throws Error
     *
     * @api
     */
    public static function buildAST(
        DocumentNode $ast,
        ?callable $typeConfigDecorator = null,
        array $options = []
    ): Schema {
        $builder = new self($ast, $typeConfigDecorator, $options);

        return $builder->buildSchema();
    }

    public function buildSchema(): Schema
    {
        $options = $this->options;
        if (! ($options['assumeValid'] ?? false) && ! ($options['assumeValidSDL'] ?? false)) {
            DocumentValidator::assertValidSDL($this->ast);
        }

        $schemaDef     = null;
        $this->nodeMap = [];
        /** @var array<int, DirectiveDefinitionNode> $directiveDefs */
        $directiveDefs = [];
        foreach ($this->ast->definitions as $definition) {
            switch (true) {
                case $definition instanceof SchemaDefinitionNode:
                    $schemaDef = $definition;
                    break;
                case $definition instanceof TypeDefinitionNode:
                    $typeName = $definition->name->value;
                    if (isset($this->nodeMap[$typeName])) {
                        throw new Error('Type "' . $typeName . '" was defined more than once.');
                    }

                    $this->nodeMap[$typeName] = $definition;
                    break;
                case $definition instanceof DirectiveDefinitionNode:
                    $directiveDefs[] = $definition;
                    break;
            }
        }

        $operationTypes = $schemaDef !== null
            ? $this->getOperationTypes($schemaDef)
            : [
                'query'        => isset($this->nodeMap['Query']) ? 'Query' : null,
                'mutation'     => isset($this->nodeMap['Mutation']) ? 'Mutation' : null,
                'subscription' => isset($this->nodeMap['Subscription']) ? 'Subscription' : null,
            ];

        $definitionBuilder = new ASTDefinitionBuilder(
            $this->nodeMap,
            $this->options,
            static function (string $typeName): void {
                throw self::unknownType($typeName);
            },
            $this->typeConfigDecorator
        );

        $directives = array_map(
            [$definitionBuilder, 'buildDirective'],
            $directiveDefs
        );

        // If specified directives were not explicitly declared, add them.
        $directivesByName = Utils::groupBy(
            $directives,
            static function (Directive $directive): string {
                return $directive->name;
            }
        );
        if (! isset($directivesByName['skip'])) {
            $directives[] = Directive::skipDirective();
        }

        if (! isset($directivesByName['include'])) {
            $directives[] = Directive::includeDirective();
        }

        if (! isset($directivesByName['deprecated'])) {
            $directives[] = Directive::deprecatedDirective();
        }

        // Note: While this could make early assertions to get the correctly
        // typed values below, that would throw immediately while type system
        // validation with validateSchema() will produce more actionable results.

        return new Schema([
            'query'        => isset($operationTypes['query'])
                ? $definitionBuilder->buildType($operationTypes['query'])
                : null,
            'mutation'     => isset($operationTypes['mutation'])
                ? $definitionBuilder->buildType($operationTypes['mutation'])
                : null,
            'subscription' => isset($operationTypes['subscription'])
                ? $definitionBuilder->buildType($operationTypes['subscription'])
                : null,
            'typeLoader'   => static fn (string $name): Type => $definitionBuilder->buildType($name),
            'directives'   => $directives,
            'astNode'      => $schemaDef,
            'types'        => fn (): array => array_map(
                static fn (TypeDefinitionNode $def): Type => $definitionBuilder->buildType($def->name->value),
                $this->nodeMap,
            ),
        ]);
    }

    /**
     * @return array<string, string>
     *
     * @throws Error
     */
    private function getOperationTypes(SchemaDefinitionNode $schemaDef): array
    {
        $opTypes = [];

        foreach ($schemaDef->operationTypes as $operationType) {
            $typeName  = $operationType->type->name->value;
            $operation = $operationType->operation;

            if (isset($opTypes[$operation])) {
                throw new Error('Must provide only one ' . $operation . ' type in schema.');
            }

            if (! isset($this->nodeMap[$typeName])) {
                throw new Error('Specified ' . $operation . ' type "' . $typeName . '" not found in document.');
            }

            $opTypes[$operation] = $typeName;
        }

        return $opTypes;
    }

    public static function unknownType(string $typeName): Error
    {
        return new Error('Unknown type: "' . $typeName . '".');
    }
}
