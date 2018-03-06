<?php
namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\Directive;

/**
 * Build instance of `GraphQL\Type\Schema` out of type language definition (string or parsed AST)
 * See [section in docs](type-system/type-language.md) for details.
 */
class BuildSchema
{
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
     *
     *
     * @api
     * @param DocumentNode $ast
     * @param callable $typeConfigDecorator
     * @param array $options
     * @return Schema
     * @throws Error
     */
    public static function buildAST(DocumentNode $ast, callable $typeConfigDecorator = null, array $options = [])
    {
        $builder = new self($ast, $typeConfigDecorator, $options);
        return $builder->buildSchema();
    }

    private $ast;
    private $nodeMap;
    private $typeConfigDecorator;
    private $options;

    public function __construct(DocumentNode $ast, callable $typeConfigDecorator = null, array $options = [])
    {
        $this->ast = $ast;
        $this->typeConfigDecorator = $typeConfigDecorator;
        $this->options = $options;
    }

    public function buildSchema()
    {
        /** @var SchemaDefinitionNode $schemaDef */
        $schemaDef = null;
        $typeDefs = [];
        $this->nodeMap = [];
        $directiveDefs = [];
        foreach ($this->ast->definitions as $d) {
            switch ($d->kind) {
                case NodeKind::SCHEMA_DEFINITION:
                    if ($schemaDef) {
                        throw new Error('Must provide only one schema definition.');
                    }
                    $schemaDef = $d;
                    break;
                case NodeKind::SCALAR_TYPE_DEFINITION:
                case NodeKind::OBJECT_TYPE_DEFINITION:
                case NodeKind::INTERFACE_TYPE_DEFINITION:
                case NodeKind::ENUM_TYPE_DEFINITION:
                case NodeKind::UNION_TYPE_DEFINITION:
                case NodeKind::INPUT_OBJECT_TYPE_DEFINITION:
                    $typeName = $d->name->value;
                    if (!empty($this->nodeMap[$typeName])) {
                        throw new Error("Type \"$typeName\" was defined more than once.");
                    }
                    $typeDefs[] = $d;
                    $this->nodeMap[$typeName] = $d;
                    break;
                case NodeKind::DIRECTIVE_DEFINITION:
                    $directiveDefs[] = $d;
                    break;
            }
        }

        $operationTypes = $schemaDef
            ? $this->getOperationTypes($schemaDef)
            : [
                'query' => isset($this->nodeMap['Query']) ? 'Query' : null,
                'mutation' => isset($this->nodeMap['Mutation']) ? 'Mutation' : null,
                'subscription' => isset($this->nodeMap['Subscription']) ? 'Subscription' : null,
            ];

        $defintionBuilder = new ASTDefinitionBuilder(
            $this->nodeMap,
            $this->options,
            function($typeName) { throw new Error('Type "'. $typeName . '" not found in document.'); },
            $this->typeConfigDecorator
        );

        $directives = array_map(function($def) use ($defintionBuilder) {
            return $defintionBuilder->buildDirective($def);
        }, $directiveDefs);

        // If specified directives were not explicitly declared, add them.
        $skip = array_reduce($directives, function ($hasSkip, $directive) {
            return $hasSkip || $directive->name == 'skip';
        });
        if (!$skip) {
            $directives[] = Directive::skipDirective();
        }

        $include = array_reduce($directives, function ($hasInclude, $directive) {
            return $hasInclude || $directive->name == 'include';
        });
        if (!$include) {
            $directives[] = Directive::includeDirective();
        }

        $deprecated = array_reduce($directives, function ($hasDeprecated, $directive) {
            return $hasDeprecated || $directive->name == 'deprecated';
        });
        if (!$deprecated) {
            $directives[] = Directive::deprecatedDirective();
        }

        // Note: While this could make early assertions to get the correctly
        // typed values below, that would throw immediately while type system
        // validation with validateSchema() will produce more actionable results.

        $schema = new Schema([
            'query' => isset($operationTypes['query'])
                ? $defintionBuilder->buildType($operationTypes['query'])
                : null,
            'mutation' => isset($operationTypes['mutation'])
                ? $defintionBuilder->buildType($operationTypes['mutation'])
                : null,
            'subscription' => isset($operationTypes['subscription'])
                ? $defintionBuilder->buildType($operationTypes['subscription'])
                : null,
            'typeLoader' => function ($name) use ($defintionBuilder) {
                return $defintionBuilder->buildType($name);
            },
            'directives' => $directives,
            'astNode' => $schemaDef,
            'types' => function () use ($defintionBuilder) {
                $types = [];
                foreach ($this->nodeMap as $name => $def) {
                    $types[] = $defintionBuilder->buildType($def->name->value);
                }
                return $types;
            }
        ]);

        return $schema;
    }

    /**
     * @param SchemaDefinitionNode $schemaDef
     * @return array
     * @throws Error
     */
    private function getOperationTypes($schemaDef)
    {
        $opTypes = [];

        foreach ($schemaDef->operationTypes as $operationType) {
            $typeName = $operationType->type->name->value;
            $operation = $operationType->operation;

            if (isset($opTypes[$operation])) {
                throw new Error("Must provide only one $operation type in schema.");
            }

            if (!isset($this->nodeMap[$typeName])) {
                throw new Error("Specified $operation type \"$typeName\" not found in document.");
            }

            $opTypes[$operation] = $typeName;
        }

        return $opTypes;
    }

    /**
     * A helper function to build a GraphQLSchema directly from a source
     * document.
     *
     * @api
     * @param DocumentNode|Source|string $source
     * @param callable $typeConfigDecorator
     * @param array $options
     * @return Schema
     */
    public static function build($source, callable $typeConfigDecorator = null, array $options = [])
    {
        $doc = $source instanceof DocumentNode ? $source : Parser::parse($source);
        return self::buildAST($doc, $typeConfigDecorator, $options);
    }
}
