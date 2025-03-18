<?php declare(strict_types=1);

namespace GraphQL\Benchmarks\Utils;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;

class SchemaGenerator
{
    /** @var array<string, int> */
    private array $config = [
        'totalTypes' => 100,
        'nestingLevel' => 10,
        'fieldsPerType' => 10,
        'listFieldsPerType' => 2,
    ];

    private int $typeIndex = 0;

    /** @var array<string, ObjectType> */
    private array $objectTypes = [];

    /** @param array<string, int> $config */
    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    public function buildSchema(): Schema
    {
        return new Schema(
            (new SchemaConfig())
                ->setQuery($this->buildQueryType())
        );
    }

    public function buildQueryType(): ObjectType
    {
        $this->typeIndex = 0;
        $this->objectTypes = [];

        return $this->createType(0);
    }

    public function loadType(string $name): ObjectType
    {
        $tokens = explode('_', $name);
        $nestingLevel = (int) $tokens[1];

        return $this->createType($nestingLevel, $name);
    }

    protected function createType(int $nestingLevel, ?string $typeName = null): ObjectType
    {
        if ($this->typeIndex > $this->config['totalTypes']) {
            throw new \Exception("Cannot create new type: there are already {$this->typeIndex} types which exceeds allowed number of {$this->config['totalTypes']} types total");
        }

        ++$this->typeIndex;
        if ($typeName === null) {
            $typeName = 'Level_' . $nestingLevel . '_Type' . $this->typeIndex;
        }

        $type = new ObjectType([
            'name' => $typeName,
            'fields' => fn (): array => $this->createTypeFields($typeName, $nestingLevel + 1),
        ]);

        $this->objectTypes[$typeName] = $type;

        return $type;
    }

    /** @return array{0: Type, 1: string} */
    protected function getFieldTypeAndName(int $nestingLevel, int $fieldIndex): array
    {
        if ($nestingLevel >= $this->config['nestingLevel']) {
            $fieldType = Type::string();
            $fieldName = 'leafField' . $fieldIndex;
        } elseif ($this->typeIndex >= $this->config['totalTypes']) {
            $fieldType = $this->objectTypes[array_rand($this->objectTypes)];
            $fieldName = 'randomTypeField' . $fieldIndex;
        } else {
            $fieldType = $this->createType($nestingLevel);
            $fieldName = 'field' . $fieldIndex;
        }

        return [$fieldType, $fieldName];
    }

    /** @return array<int, array<string, mixed>> */
    protected function createTypeFields(string $typeName, int $nestingLevel): array
    {
        $fields = [];
        for ($index = 0; $index < $this->config['fieldsPerType']; ++$index) {
            [$type, $name] = $this->getFieldTypeAndName($nestingLevel, $index);
            $fields[] = [
                'name' => $name,
                'type' => $type,
                'resolve' => [$this, 'resolveField'],
            ];
        }

        for ($index = 0; $index < $this->config['listFieldsPerType']; ++$index) {
            [$type, $name] = $this->getFieldTypeAndName($nestingLevel, $index);
            $name = 'listOf' . ucfirst($name);

            $fields[] = [
                'name' => $name,
                'type' => Type::listOf($type),
                'args' => $this->createFieldArgs($name, $typeName),
                'resolve' => static fn (): array => [
                    'string1',
                    'string2',
                    'string3',
                    'string4',
                    'string5',
                ],
            ];
        }

        return $fields;
    }

    /** @return array<string, mixed> */
    protected function createFieldArgs(string $fieldName, string $typeName): array
    {
        return [
            'argString' => [
                'type' => Type::string(),
            ],
            'argEnum' => [
                'type' => new EnumType([
                    'name' => $typeName . $fieldName . 'Enum',
                    'values' => [
                        'ONE',
                        'TWO',
                        'THREE',
                    ],
                ]),
            ],
            'argInputObject' => [
                'type' => new InputObjectType([
                    'name' => $typeName . $fieldName . 'Input',
                    'fields' => [
                        'field1' => Type::string(),
                        'field2' => Type::int(),
                    ],
                ]),
            ],
        ];
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param mixed $context
     */
    public function resolveField($root, array $args, $context, ResolveInfo $resolveInfo): string
    {
        return $resolveInfo->fieldName . '-value';
    }
}
