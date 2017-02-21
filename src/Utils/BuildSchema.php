<?php
namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Token;
use GraphQL\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Utils;

/**
 * Class BuildSchema
 * @package GraphQL\Utils
 */
class BuildSchema
{
    /**
     * @param Type $innerType
     * @param TypeNode $inputTypeNode
     * @return Type 
     */
    private function buildWrappedType(Type $innerType, TypeNode $inputTypeNode)
    {
        if ($inputTypeNode->kind == NodeKind::LIST_TYPE) {
            return Type::listOf($this->buildWrappedType($innerType, $inputTypeNode->type));
        }
        if ($inputTypeNode->kind == NodeKind::NON_NULL_TYPE) {
            $wrappedType = $this->buildWrappedType($innerType, $inputTypeNode->type);
            Utils::invariant(!($wrappedType instanceof NonNull), 'No nesting nonnull.');
            return Type::nonNull($wrappedType);
        }
        return $innerType;
    }

    private function getNamedTypeNode(TypeNode $typeNode)
    {
        $namedType = $typeNode;
        while ($namedType->kind === NodeKind::LIST_TYPE || $namedType->kind === NodeKind::NON_NULL_TYPE) {
            $namedType = $namedType->type;
        }
        return $namedType;
    }

    /**
     * This takes the ast of a schema document produced by the parse function in
     * GraphQL\Language\Parser.
     *
     * If no schema definition is provided, then it will look for types named Query
     * and Mutation.
     *
     * Given that AST it constructs a GraphQLSchema. The resulting schema
     * has no resolve methods, so execution will use default resolvers.
     *
     * @param DocumentNode $ast
     * @return Schema
     * @throws Error
     */
    public static function buildAST(DocumentNode $ast)
    {
        $builder = new self($ast);
        return $builder->buildSchema();
    }

    private $ast;
    private $innerTypeMap;
    private $nodeMap;

    public function __construct(DocumentNode $ast)
    {
        $this->ast = $ast;
    }
    
    public function buildSchema()
    {
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
                    $typeDefs[] = $d;
                    $this->nodeMap[$d->name->value] = $d;
                    break;
                case NodeKind::DIRECTIVE_DEFINITION:
                    $directiveDefs[] = $d;
                    break;
            }
        }

        $queryTypeName = null;
        $mutationTypeName = null;
        $subscriptionTypeName = null;
        if ($schemaDef) {
            foreach ($schemaDef->operationTypes as $operationType) {
                $typeName = $operationType->type->name->value;
                if ($operationType->operation === 'query') {
                    if ($queryTypeName) {
                        throw new Error('Must provide only one query type in schema.');
                    }
                    if (!isset($this->nodeMap[$typeName])) {
                        throw new Error(
                            'Specified query type "' . $typeName . '" not found in document.'
                        );
                    }
                    $queryTypeName = $typeName;
                } else if ($operationType->operation === 'mutation') {
                    if ($mutationTypeName) {
                        throw new Error('Must provide only one mutation type in schema.');
                    }
                    if (!isset($this->nodeMap[$typeName])) {
                        throw new Error(
                            'Specified mutation type "' . $typeName . '" not found in document.'
                        );
                    }
                    $mutationTypeName = $typeName;
                } else if ($operationType->operation === 'subscription') {
                    if ($subscriptionTypeName) {
                        throw new Error('Must provide only one subscription type in schema.');
                    }
                    if (!isset($this->nodeMap[$typeName])) {
                        throw new Error(
                            'Specified subscription type "' . $typeName . '" not found in document.'
                        );
                    }
                    $subscriptionTypeName = $typeName;
                }
            }
        } else {
            if (isset($this->nodeMap['Query'])) {
                $queryTypeName = 'Query';
            }
            if (isset($this->nodeMap['Mutation'])) {
                $mutationTypeName = 'Mutation';
            }
            if (isset($this->nodeMap['Subscription'])) {
                $subscriptionTypeName = 'Subscription';
            }
        }

        if (!$queryTypeName) {
            throw new Error(
                'Must provide schema definition with query type or a type named Query.'
            );
        }

        $this->innerTypeMap = [
            'String' => Type::string(),
            'Int' => Type::int(),
            'Float' => Type::float(),
            'Boolean' => Type::boolean(),
            'ID' => Type::id(),
            '__Schema' => Introspection::_schema(),
            '__Directive' => Introspection::_directive(),
            '__DirectiveLocation' => Introspection::_directiveLocation(),
            '__Type' => Introspection::_type(),
            '__Field' => Introspection::_field(),
            '__InputValue' => Introspection::_inputValue(),
            '__EnumValue' => Introspection::_enumValue(),
            '__TypeKind' => Introspection::_typeKind(),
        ];

        $types = array_map(function($def) {
            return $this->typeDefNamed($def->name->value);
        }, $typeDefs);

        $directives = array_map([$this, 'getDirective'], $directiveDefs);

        // If specified directives were not explicitly declared, add them.
        $skip = array_reduce($directives, function($hasSkip, $directive) {
            return $hasSkip || $directive->name == 'skip';
        });
        if (!$skip) {
            $directives[] = Directive::skipDirective();
        }

        $include = array_reduce($directives, function($hasInclude, $directive) {
            return $hasInclude || $directive->name == 'include';
        });
        if (!$include) {
            $directives[] = Directive::includeDirective();
        }

        $deprecated = array_reduce($directives, function($hasDeprecated, $directive) {
            return $hasDeprecated || $directive->name == 'deprecated';
        });
        if (!$deprecated) {
            $directives[] = Directive::deprecatedDirective();
        }

        return new Schema([
            'query' => $this->getObjectType($this->nodeMap[$queryTypeName]),
            'mutation' => $mutationTypeName ?
                $this->getObjectType($this->nodeMap[$mutationTypeName]) :
                null,
            'subscription' => $subscriptionTypeName ?
                $this->getObjectType($this->nodeMap[$subscriptionTypeName]) :
                null,
            'types' => $types,
            'directives' => $directives,
        ]);
    }

    private function getDirective(DirectiveDefinitionNode $directiveNode)
    {
        return new Directive([
            'name' => $directiveNode->name->value,
            'description' => $this->getDescription($directiveNode),
            'locations' => array_map(function($node) {
                return $node->value;
            }, $directiveNode->locations),
            'args' => $directiveNode->arguments ? FieldArgument::createMap($this->makeInputValues($directiveNode->arguments)) : null,
        ]);
    }

    private function getObjectType(TypeDefinitionNode $typeNode)
    {
        $type = $this->typeDefNamed($typeNode->name->value);
        Utils::invariant(
            $type instanceof ObjectType,
            'AST must provide object type.'
        );
        return $type;
    }

    private function produceType(TypeNode $typeNode)
    {
        $typeName = $this->getNamedTypeNode($typeNode)->name->value;
        $typeDef = $this->typeDefNamed($typeName);
        return $this->buildWrappedType($typeDef, $typeNode);
    }

    private function produceInputType(TypeNode $typeNode)
    {
        $type = $this->produceType($typeNode);
        Utils::invariant(Type::isInputType($type), 'Expected Input type.');
        return $type;
    }

    private function produceOutputType(TypeNode $typeNode)
    {
        $type = $this->produceType($typeNode);
        Utils::invariant(Type::isOutputType($type), 'Expected Input type.');
        return $type;
    }

    private function produceObjectType(TypeNode $typeNode)
    {
        $type = $this->produceType($typeNode);
        Utils::invariant($type instanceof ObjectType, 'Expected Object type.');
        return $type;
    }

    private function produceInterfaceType(TypeNode $typeNode)
    {
        $type = $this->produceType($typeNode);
        Utils::invariant($type instanceof InterfaceType, 'Expected Input type.');
        return $type;
    }

    private function typeDefNamed($typeName)
    {
        if (isset($this->innerTypeMap[$typeName])) {
            return $this->innerTypeMap[$typeName];
        }

        if (!isset($this->nodeMap[$typeName])) {
            throw new Error('Type "' . $typeName . '" not found in document.');
        }

        $innerTypeDef = $this->makeSchemaDef($this->nodeMap[$typeName]);
        if (!$innerTypeDef) {
            throw new Error("Nothing constructed for $typeName.");
        }
        $this->innerTypeMap[$typeName] = $innerTypeDef;
        return $innerTypeDef;
    }

    private function makeSchemaDef($def)
    {
        if (!$def) {
            throw new Error('def must be defined.');
        }
        switch ($def->kind) {
            case NodeKind::OBJECT_TYPE_DEFINITION:
                return $this->makeTypeDef($def);
            case NodeKind::INTERFACE_TYPE_DEFINITION:
                return $this->makeInterfaceDef($def);
            case NodeKind::ENUM_TYPE_DEFINITION:
                return $this->makeEnumDef($def);
            case NodeKind::UNION_TYPE_DEFINITION:
                return $this->makeUnionDef($def);
            case NodeKind::SCALAR_TYPE_DEFINITION:
                return $this->makeScalarDef($def);
            case NodeKind::INPUT_OBJECT_TYPE_DEFINITION:
                return $this->makeInputObjectDef($def);
            default:
                throw new Error("Type kind of {$def->kind} not supported.");
        }
    }

    private function makeTypeDef(ObjectTypeDefinitionNode $def)
    {
        $typeName = $def->name->value;
        return new ObjectType([
            'name' => $typeName,
            'description' => $this->getDescription($def),
            'fields' => function() use ($def) { return $this->makeFieldDefMap($def); },
            'interfaces' => function() use ($def) { return $this->makeImplementedInterfaces($def); }
        ]);
    }

    private function makeFieldDefMap($def)
    {
        return Utils::keyValMap(
            $def->fields,
            function ($field) {
                return $field->name->value;
            },
            function($field) {
                return [
                    'type' => $this->produceOutputType($field->type),
                    'description' => $this->getDescription($field),
                    'args' => $this->makeInputValues($field->arguments),
                    'deprecationReason' => $this->getDeprecationReason($field->directives)
                ];
            }
        );
    }

    private function makeImplementedInterfaces(ObjectTypeDefinitionNode $def)
    {
        return isset($def->interfaces) ? array_map([$this, 'produceInterfaceType'], $def->interfaces) : null;
    }

    private function makeInputValues($values)
    {
        return Utils::keyValMap(
            $values,
            function ($value) {
                return $value->name->value;
            },
            function($value) {
                $type = $this->produceInputType($value->type);
                $config = [
                    'name' => $value->name->value,
                    'type' => $type,
                    'description' => $this->getDescription($value)
                ];
                if (isset($value->defaultValue)) {
                    $config['defaultValue'] = Utils\AST::valueFromAST($value->defaultValue, $type);
                }
                return $config;
            }
        );
    }

    private function makeInterfaceDef(InterfaceTypeDefinitionNode $def)
    {
        $typeName = $def->name->value;
        return new InterfaceType([
            'name' => $typeName,
            'description' => $this->getDescription($def),
            'fields' => function() use ($def) { return $this->makeFieldDefMap($def); },
            'resolveType' => [$this, 'cannotExecuteSchema']
        ]);
    }

    private function makeEnumDef(EnumTypeDefinitionNode $def)
    {
        return new EnumType([
            'name' => $def->name->value,
            'description' => $this->getDescription($def),
            'values' => Utils::keyValMap(
                $def->values,
                function($enumValue) {
                    return $enumValue->name->value;
                },
                function($enumValue) {
                    return [
                        'description' => $this->getDescription($enumValue),
                        'deprecationReason' => $this->getDeprecationReason($enumValue->directives)
                    ];
                }
            )
        ]);
    }

    private function makeUnionDef(UnionTypeDefinitionNode $def)
    {
        return new UnionType([
            'name' => $def->name->value,
            'description' => $this->getDescription($def),
            'types' => array_map([$this, 'produceObjectType'], $def->types),
            'resolveType' => [$this, 'cannotExecuteSchema']
        ]);
    }

    private function makeScalarDef(ScalarTypeDefinitionNode $def)
    {
        return new CustomScalarType([
            'name' => $def->name->value,
            'description' => $this->getDescription($def),
            'serialize' => function() { return false; },
            // Note: validation calls the parse functions to determine if a
            // literal value is correct. Returning null would cause use of custom
            // scalars to always fail validation. Returning false causes them to
            // always pass validation.
            'parseValue' => function() { return false; },
            'parseLiteral' => function() { return false; }
        ]);
    }

    private function makeInputObjectDef(InputObjectTypeDefinitionNode $def)
    {
        return new InputObjectType([
            'name' => $def->name->value,
            'description' => $this->getDescription($def),
            'fields' => function() use ($def) { return $this->makeInputValues($def->fields); }
        ]);
    }

    private function getDeprecationReason($directives)
    {
        $deprecatedAST = $directives ? Utils::find(
            $directives,
            function($directive) {
                return $directive->name->value === Directive::deprecatedDirective()->name;
            }
        ) : null;
        if (!$deprecatedAST) {
            return;
        }
        return Values::getArgumentValues(
            Directive::deprecatedDirective(),
            $deprecatedAST
        )['reason'];
    }

    /**
     * Given an ast node, returns its string description based on a contiguous
     * block full-line of comments preceding it.
     */
    public function getDescription($node)
    {
        $loc = $node->loc;
        if (!$loc) {
            return;
        }
        $comments = [];
        $minSpaces = null;
        $token = $loc->startToken->prev;
        while (
            $token &&
            $token->kind === Token::COMMENT &&
            $token->next && $token->prev &&
            $token->line + 1 === $token->next->line &&
            $token->line !== $token->prev->line
        ) {
            $value = $token->value;
            $spaces = $this->leadingSpaces($value);
            if ($minSpaces === null || $spaces < $minSpaces) {
                $minSpaces = $spaces;
            }
            $comments[] = $value;
            $token = $token->prev;
        }
        return implode("\n", array_map(function($comment) use ($minSpaces) {
            return mb_substr(str_replace("\n", '', $comment), $minSpaces);
        }, array_reverse($comments)));
    }

    /**
     * A helper function to build a GraphQLSchema directly from a source
     * document.
     * 
     * @param Source|string $source
     * @return
     */
    public static function build($source)
    {
        return self::buildAST(Parser::parse($source));
    }

    // Count the number of spaces on the starting side of a string.
    private function leadingSpaces($str)
    {
        return strlen($str) - strlen(ltrim($str));
    }

    public function cannotExecuteSchema() {
        throw new Error(
            'Generated Schema cannot use Interface or Union types for execution.'
        );
    }

}