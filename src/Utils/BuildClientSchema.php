<?php

declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\OutputType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Type\TypeKind;
use stdClass;
use function array_map;
use function array_merge;
use function json_encode;
use function property_exists;

class BuildClientSchema
{
    /** @var stdClass */
    private $introspection;

    /** @var bool[] */
    private $options;

    /** @var NamedType[] */
    private $typeMap;

    /**
     * @param bool[] $options
     *
     * @paran \stdClass $introspectionQuery
     */
    public function __construct(stdClass $introspectionQuery, array $options = [])
    {
        $this->introspection = $introspectionQuery;
        $this->options       = $options;
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
    public static function build(stdClass $introspectionQuery, array $options = []) : Schema
    {
        $builder = new self($introspectionQuery, $options);

        return $builder->buildSchema();
    }

    public function buildSchema()
    {
        if (! property_exists($this->introspection, '__schema')) {
            throw new InvariantViolation('Invalid or incomplete introspection result. Ensure that you are passing "data" property of introspection response and no "errors" was returned alongside: ' . json_encode($this->introspection));
        }

        $schemaIntrospection = $this->introspection->__schema;

        $this->typeMap = Utils::keyValMap(
            $schemaIntrospection->types,
            static function (stdClass $typeIntrospection) {
                return $typeIntrospection->name;
            },
            function (stdClass $typeIntrospection) {
                return $this->buildType($typeIntrospection);
            }
        );

        $builtInTypes = array_merge(
            Type::getStandardTypes(),
            Introspection::getTypes()
        );
        foreach ($builtInTypes as $name => $type) {
            if (! isset($this->typeMap[$name])) {
                continue;
            }

            $this->typeMap[$name] = $type;
        }

        $queryType = $schemaIntrospection->queryType
            ? $this->getObjectType($schemaIntrospection->queryType)
            : null;

        $mutationType = $schemaIntrospection->mutationType
            ? $this->getObjectType($schemaIntrospection->mutationType)
            : null;

        $subscriptionType = $schemaIntrospection->subscriptionType
            ? $this->getObjectType($schemaIntrospection->subscriptionType)
            : null;

        $directives = $schemaIntrospection->directives
            ? array_map(
                [$this, 'buildDirective'],
                $schemaIntrospection->directives
            )
            : [];

        $schemaConfig = new SchemaConfig();
        $schemaConfig->setQuery($queryType)
            ->setMutation($mutationType)
            ->setSubscription($subscriptionType)
            ->setTypes($this->typeMap)
            ->setDirectives($directives)
            ->setAssumeValid(
                isset($this->options)
                && isset($this->options['assumeValid'])
                && $this->options['assumeValid']
            );

        return new Schema($schemaConfig);
    }

    private function getType(stdClass $typeRef) : Type
    {
        if ($typeRef->kind === TypeKind::LIST) {
            $itemRef = $typeRef->ofType;
            if (! $itemRef) {
                throw new InvariantViolation('Decorated type deeper than introspection query.');
            }

            return new ListOfType($this->getType($itemRef));
        }

        if ($typeRef->kind === TypeKind::NON_NULL) {
            $nullableRef = $typeRef->ofType;
            if (! $nullableRef) {
                throw new InvariantViolation('Decorated type deeper than introspection query.');
            }
            $nullableType = $this->getType($nullableRef);

            return new NonNull(
                NonNull::assertNullableType($nullableType)
            );
        }

        if (! $typeRef->name) {
            throw new InvariantViolation('Unknown type reference: ' . json_encode($typeRef));
        }

        return $this->getNamedType($typeRef->name);
    }

    private function getNamedType(string $typeName) : NamedType
    {
        if (! isset($this->typeMap[$typeName])) {
            throw new InvariantViolation(
                "Invalid or incomplete schema, unknown type: ${typeName}. Ensure that a full introspection query is used in order to build a client schema."
            );
        }

        return $this->typeMap[$typeName];
    }

    private function getInputType(stdClass $typeRef) : InputType
    {
        $type = $this->getType($typeRef);

        if ($type instanceof InputType) {
            return $type;
        }

        throw new InvariantViolation('Introspection must provide input type for arguments, but received: ' . json_encode($type));
    }

    private function getOutputType(stdClass $typeRef) : OutputType
    {
        $type = $this->getType($typeRef);

        if ($type instanceof OutputType) {
            return $type;
        }

        throw new InvariantViolation('Introspection must provide output type for fields, but received: ' . json_encode($type));
    }

    private function getObjectType(stdClass $typeRef) : ObjectType
    {
        $type = $this->getType($typeRef);

        return ObjectType::assertObjectType($type);
    }

    private function getInterfaceType(stdClass $typeRef) : InterfaceType
    {
        $type = $this->getType($typeRef);

        return InterfaceType::assertInterfaceType($type);
    }

    private function buildType(stdClass $type) : NamedType
    {
        if (property_exists($type, 'name') && property_exists($type, 'kind')) {
            switch ($type->kind) {
                case TypeKind::SCALAR:
                    return $this->buildScalarDef($type);
                case TypeKind::OBJECT:
                    return $this->buildObjectDef($type);
                case TypeKind::INTERFACE:
                    return $this->buildInterfaceDef($type);
                case TypeKind::UNION:
                    return $this->buildUnionDef($type);
                case TypeKind::ENUM:
                    return $this->buildEnumDef($type);
                case TypeKind::INPUT_OBJECT:
                    return $this->buildInputObjectDef($type);
            }
        }

        throw new InvariantViolation(
            'Invalid or incomplete introspection result. Ensure that a full introspection query is used in order to build a client schema: ' . json_encode($type)
        );
    }

    private function buildScalarDef(stdClass $scalar) : ScalarType
    {
        return new CustomScalarType([
            'name' => $scalar->name,
            'description' => $scalar->description,
        ]);
    }

    private function buildObjectDef(stdClass $object) : ObjectType
    {
        if (! property_exists($object, 'interfaces')) {
            throw new InvariantViolation('Introspection result missing interfaces: ' . json_encode($object));
        }

        return new ObjectType([
            'name' => $object->name,
            'description' => $object->description,
            'interfaces' => function () use ($object) {
                return array_map(
                    [$this, 'getInterfaceType'],
                    $object->interfaces
                );
            },
            'fields' => function () use ($object) {
                return $this->buildFieldDefMap($object);
            },
        ]);
    }

    private function buildInterfaceDef(stdClass $interface) : InterfaceType
    {
        return new InterfaceType([
            'name' => $interface->name,
            'description' => $interface->description,
            'fields' => function () use ($interface) {
                return $this->buildFieldDefMap($interface);
            },
        ]);
    }

    private function buildUnionDef(stdClass $union) : UnionType
    {
        if (! property_exists($union, 'possibleType')) {
            throw new InvariantViolation('Introspection result missing possibleTypes: ' . json_encode($union));
        }

        return new UnionType([
            'name' => $union->name,
            'description' => $union->description,
            'types' => function () use ($union) {
                return array_map(
                    [$this, 'getObjectType'],
                    $union->possibleTypes
                );
            },
        ]);
    }

    private function buildEnumDef(stdClass $enum) : EnumType
    {
        if (! property_exists($enum, 'enumValues')) {
            throw new InvariantViolation('Introspection result missing enumValues: ' . json_encode($enum));
        }

        return new EnumType([
            'name' => $enum->name,
            'description' => $enum->description,
            'values' => Utils::keyValMap(
                $enum->enumValues,
                static function (stdClass $enumValue) : string {
                    return $enumValue->name;
                },
                static function (stdClass $enumValue) {
                    return [
                        'description' => $enumValue->description,
                        'deprecationReason' => $enumValue->deprecationReason,
                    ];
                }
            ),
        ]);
    }

    private function buildInputObjectDef(stdClass $inputObject) : InputObjectType
    {
        if (! property_exists($inputObject, 'inputFields')) {
            throw new InvariantViolation('Introspection result missing inputFields: ' . json_encode($inputObject));
        }

        return new InputObjectType([
            'name' => $inputObject->name,
            'description' => $inputObject->description,
            'fields' => function () use ($inputObject) {
                return $this->buildInputValueDefMap($inputObject->inputFields);
            },
        ]);
    }

    private function buildFieldDefMap(stdClass $typeIntrospection)
    {
        if (! property_exists($typeIntrospection, 'fields')) {
            throw new InvariantViolation('Introspection result missing fields: ' . json_encode($typeIntrospection));
        }

        return Utils::keyValMap(
            $typeIntrospection->fields,
            static function (stdClass $fieldIntrospection) : string {
                return $fieldIntrospection->name;
            },
            function (stdClass $fieldIntrospection) {
                if (! property_exists($fieldIntrospection, 'args')) {
                    throw new InvariantViolation('Introspection result missing field args: ' . json_encode($fieldIntrospection));
                }

                return [
                    'description' => $fieldIntrospection->description,
                    'deprecationReason' => $fieldIntrospection->deprecationReason,
                    'type' => $this->getOutputType($fieldIntrospection->type),
                    'args' => $this->buildInputValueDefMap($fieldIntrospection->args),
                ];
            }
        );
    }

    /**
     * @param  stdClass[] $inputValueIntrospections
     *
     * @return mixed[][]
     */
    private function buildInputValueDefMap(array $inputValueIntrospections)
    {
        return Utils::keyValMap(
            $inputValueIntrospections,
            static function (stdClass $inputValue) : string {
                return $inputValue->name;
            },
            [$this, 'buildInputValue']
        );
    }

    private function buildInputValue(stdClass $inputValueIntrospection)
    {
        $type = $this->getInputType($inputValueIntrospection->type);

        $inputValue = [
            'description' => $inputValueIntrospection->description,
            'type' => $type,
        ];

        if (! $inputValueIntrospection->defaultValue) {
            return;
        }

        $inputValue['defaultValue'] = AST::valueFromAST(
            Parser::parseValue($inputValueIntrospection->defaultValue),
            $type
        );
    }

    private function buildDirective(stdClass $directive) : Directive
    {
        if (! property_exists($directive, 'args')) {
            throw new InvariantViolation('Introspection result missing directive args: ' . json_encode($directive));
        }
        if (! property_exists($directive, 'locations')) {
            throw new InvariantViolation('Introspection result missing directive locations: ' . json_encode($directive));
        }

        return new Directive([
            'name' => $directive->name,
            'description' => $directive->description,
            'locations' => $directive->locations,
            'args' => $this->buildInputValueDefMap($directive->args),
        ]);
    }
}
