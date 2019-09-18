<?php

declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\TypeKind;
use GraphQL\Validator\DocumentValidator;
use function array_map;
use function array_reduce;
use function sprintf;

class BuildClientSchema
{
    /** @var \stdClass */
    private $introspection;

    /** @var TypeDefinitionNode[] */
    private $nodeMap;

    /** @var callable|null */
    private $typeConfigDecorator;

    /** @var bool[] */
    private $options;

    /**
     * @paran \stdClass $introspectionQuery
     * @param bool[] $options
     */
    public function __construct(\stdClass $introspectionQuery, ?callable $typeConfigDecorator = null, array $options = [])
    {
        $this->introspection                 = $introspectionQuery;
        $this->typeConfigDecorator = $typeConfigDecorator;
        $this->options             = $options;
    }

    /**
     * Build a GraphQLSchema for use by client tools.
     *
     * Given the result of a client running the introspection query, creates and
     * returns a GraphQLSchema instance which can be then used with all graphql-js
     * tools, but cannot be used to execute a query, as introspection does not
     * represent the "resolver", "parse" or "serialize" functions or any other
     * server-internal mechanisms.
     *
     * This function expects a complete introspection result. Don't forget to check
     * the "errors" field of a server response before calling this function.
     *
     * Accepts options as a third argument:
     *
     *    - assumeValid:
     *          When building a schema from a GraphQL service's introspection result, it
     *          might be safe to assume the schema is valid. Set to true to assume the
     *          produced schema is valid.
     *
     *          Default: false
     *
     * @param bool[] $options
     *
     * @throws Error
     *
     * @api
     */
    public static function build(\stdClass $introspectionQuery, ?callable $typeConfigDecorator = null, array $options = []): Schema
    {
        $builder = new self($introspectionQuery, $typeConfigDecorator, $options);

        return $builder->buildSchema();
    }

    public function buildSchema()
    {
        if(! property_exists($this->introspection, '__schema')) {
            throw new InvariantViolation('Invalid or incomplete introspection result. Ensure that you are passing "data" property of introspection response and no "errors" was returned alongside: ' . json_encode($this->introspection));
        }

        $schemaIntrospection = $this->introspection->__schema;

        Utils::keyValMap(
            $schemaIntrospection->types,
            static function (\stdClass $typeIntrospection) {
                return $typeIntrospection->name;
            },
            static function (\stdClass $typeIntrospection) {
                return $this->buildType($typeIntrospection);
            }
        );

        $schemaDef     = null;
        $typeDefs      = [];
        $this->nodeMap = [];
        $directiveDefs = [];
        foreach ($this->ast->definitions as $definition) {
            switch (true) {
                case $definition instanceof SchemaDefinitionNode:
                    $schemaDef = $definition;
                    break;
                case $definition instanceof ScalarTypeDefinitionNode:
                case $definition instanceof ObjectTypeDefinitionNode:
                case $definition instanceof InterfaceTypeDefinitionNode:
                case $definition instanceof EnumTypeDefinitionNode:
                case $definition instanceof UnionTypeDefinitionNode:
                case $definition instanceof InputObjectTypeDefinitionNode:
                    $typeName = $definition->name->value;
                    if (! empty($this->nodeMap[$typeName])) {
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
            static function ($typeName) {
                throw new Error('Type "' . $typeName . '" not found in document.');
            },
            $this->typeConfigDecorator
        );

        $directives = array_map(
            static function ($def) use ($DefinitionBuilder) {
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
            'typeLoader'   => static function ($name) use ($DefinitionBuilder) {
                return $DefinitionBuilder->buildType($name);
            },
            'directives'   => $directives,
            'astNode'      => $schemaDef,
            'types'        => function () use ($DefinitionBuilder) {
                $types = [];
                /** @var ScalarTypeDefinitionNode|ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|UnionTypeDefinitionNode|EnumTypeDefinitionNode|InputObjectTypeDefinitionNode $def */
                foreach ($this->nodeMap as $name => $def) {
                    $types[] = $DefinitionBuilder->buildType($def->name->value);
                }

                return $types;
            },
        ]);
    }

    private function buildType(\stdClass $type): NamedType
    {
        if(property_exists($type, 'name') && property_exists($type, 'kind')) {
            switch($type->kind) {
                case TypeKind::SCALAR:
                    return $this->buildScalarDef($type);
            }
        }
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

    private function buildScalarDef(\stdClass $scalar): ScalarType
    {
        $name = $scalar->name;

        $standardTypes = Type::getStandardTypes();
        if(isset($standardTypes[$name])) {
            return $standardTypes[$name];
        }

        return new CustomScalarType([
            'name' => $name,
            'description' => $scalar->description,
        ]);
    }
}
