<?php

declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use function array_map;
use function sprintf;

/**
 * Build instance of `GraphQL\Type\Schema` out of type language definition (string or parsed AST)
 * See [section in docs](type-system/type-language.md) for details.
 */
class BuildSchema
{
    /** @var DocumentNode */
    private $ast;

    /** @var array<string, TypeDefinitionNode> */
    private $nodeMap;

    /** @var callable|null */
    private $typeConfigDecorator;

    /** @var array<string, bool> */
    private $options;

    /**
     * @param array<string, bool> $options
     */
    public function __construct(DocumentNode $ast, ?callable $typeConfigDecorator = null, array $options = [])
    {
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
     *
     * @return Schema
     *
     * @api
     */
    public static function build($source, ?callable $typeConfigDecorator = null, array $options = [])
    {
        $doc = $source instanceof DocumentNode
            ? $source
            : Parser::parse($source);

        return self::buildAST($doc, $typeConfigDecorator, $options);
    }

    /**
     * This takes the ast of a schema document produced by the parse function in
     * GraphQL\Language\Parser.
     *
     * If no schema definition is provided, then it will look for types named Query
     * and Mutation.
     *
     * Given that AST it constructs a GraphQL\Type\Schema. The resulting schema
     * has no resolve methods, so execution will use default resolvers.
     *
     * Accepts options as a third argument:
     *
     *    - commentDescriptions:
     *        Provide true to use preceding comments as the description.
     *        This option is provided to ease adoption and will be removed in v16.
     *
     * @param array<string, bool> $options
     *
     * @return Schema
     *
     * @throws Error
     *
     * @api
     */
    public static function buildAST(DocumentNode $ast, ?callable $typeConfigDecorator = null, array $options = [])
    {
        $builder = new self($ast, $typeConfigDecorator, $options);

        return $builder->buildSchema();
    }

    public function buildSchema()
    {
        $options = $this->options;
        if (! ($options['assumeValid'] ?? false) && ! ($options['assumeValidSDL'] ?? false)) {
            DocumentValidator::assertValidSDL($this->ast);
        }

        $schemaDef     = null;
        $typeDefs      = [];
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
                        throw new Error(sprintf('Type "%s" was defined more than once.', $typeName));
                    }
                    $typeDefs[]               = $definition;
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

        $DefinitionBuilder = new ASTDefinitionBuilder(
            $this->nodeMap,
            $this->options,
            static function ($typeName) : void {
                throw new Error('Type "' . $typeName . '" not found in document.');
            },
            $this->typeConfigDecorator
        );

        $directives = array_map(
            static function (DirectiveDefinitionNode $def) use ($DefinitionBuilder) : Directive {
                return $DefinitionBuilder->buildDirective($def);
            },
            $directiveDefs
        );

        // If specified directives were not explicitly declared, add them.
        $directivesByName = Utils::groupBy(
            $directives,
            static function (Directive $directive) : string {
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
                ? $DefinitionBuilder->buildType($operationTypes['query'])
                : null,
            'mutation'     => isset($operationTypes['mutation'])
                ? $DefinitionBuilder->buildType($operationTypes['mutation'])
                : null,
            'subscription' => isset($operationTypes['subscription'])
                ? $DefinitionBuilder->buildType($operationTypes['subscription'])
                : null,
            'typeLoader'   => static function ($name) use ($DefinitionBuilder) : Type {
                return $DefinitionBuilder->buildType($name);
            },
            'directives'   => $directives,
            'astNode'      => $schemaDef,
            'types'        => function () use ($DefinitionBuilder) : array {
                $types = [];
                /** @var ScalarTypeDefinitionNode|ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|UnionTypeDefinitionNode|EnumTypeDefinitionNode|InputObjectTypeDefinitionNode $def */
                foreach ($this->nodeMap as $name => $def) {
                    $types[] = $DefinitionBuilder->buildType($def->name->value);
                }

                return $types;
            },
        ]);
    }

    /**
     * @param SchemaDefinitionNode $schemaDef
     *
     * @return string[]
     *
     * @throws Error
     */
    private function getOperationTypes($schemaDef)
    {
        $opTypes = [];

        foreach ($schemaDef->operationTypes as $operationType) {
            $typeName  = $operationType->type->name->value;
            $operation = $operationType->operation;

            if (isset($opTypes[$operation])) {
                throw new Error(sprintf('Must provide only one %s type in schema.', $operation));
            }

            if (! isset($this->nodeMap[$typeName])) {
                throw new Error(sprintf('Specified %s type "%s" not found in document.', $operation, $typeName));
            }

            $opTypes[$operation] = $typeName;
        }

        return $opTypes;
    }
}
