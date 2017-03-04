<?php
namespace GraphQL\Benchmarks\Utils;

use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class SchemaGenerator
{
    private $config = [
        'totalTypes' => 100,
        'nestingLevel' => 10,
        'fieldsPerType' => 10,
        'listFieldsPerType' => 2
    ];

    private $typeIndex = 0;

    private $objectTypes = [];

    /**
     * BenchmarkSchemaBuilder constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    public function buildSchema()
    {
        return new Schema([
            'query' => $this->buildQueryType()
        ]);
    }

    public function buildQueryType()
    {
        $this->typeIndex = 0;
        $this->objectTypes = [];

        return $this->createType(0);
    }

    public function loadType($name)
    {
        $tokens = explode('_', $name);
        $nestingLevel = (int) $tokens[1];

        return $this->createType($nestingLevel, $name);
    }

    protected function createType($nestingLevel, $typeName = null)
    {
        if ($this->typeIndex > $this->config['totalTypes']) {
            throw new \Exception(
                "Cannot create new type: there are already {$this->typeIndex} ".
                "which exceeds allowed number of {$this->config['totalTypes']} types total"
            );
        }

        $this->typeIndex++;
        if (!$typeName) {
            $typeName = 'Level_' . $nestingLevel . '_Type' . $this->typeIndex;
        }

        $type = new ObjectType([
            'name' => $typeName,
            'fields' => function() use ($typeName, $nestingLevel) {
                return $this->createTypeFields($typeName, $nestingLevel + 1);
            }
        ]);

        $this->objectTypes[$typeName] = $type;
        return $type;
    }

    protected function getFieldTypeAndName($nestingLevel, $fieldIndex)
    {
        if ($nestingLevel >= $this->config['nestingLevel']) {
            $fieldType = Type::string();
            $fieldName = 'leafField' . $fieldIndex;
        } else if ($this->typeIndex >= $this->config['totalTypes']) {
            $fieldType = $this->objectTypes[array_rand($this->objectTypes)];
            $fieldName = 'randomTypeField' . $fieldIndex;
        } else {
            $fieldType = $this->createType($nestingLevel);
            $fieldName = 'field' . $fieldIndex;
        }

        return [$fieldType, $fieldName];
    }

    protected function createTypeFields($typeName, $nestingLevel)
    {
        $fields = [];
        for ($index = 0; $index < $this->config['fieldsPerType']; $index++) {
            list($type, $name) = $this->getFieldTypeAndName($nestingLevel, $index);
            $fields[] = [
                'name' => $name,
                'type' => $type,
                'resolve' => [$this, 'resolveField']
            ];
        }
        for ($index = 0; $index < $this->config['listFieldsPerType']; $index++) {
            list($type, $name) = $this->getFieldTypeAndName($nestingLevel, $index);
            $name = 'listOf' . ucfirst($name);

            $fields[] = [
                'name' => $name,
                'type' => Type::listOf($type),
                'args' => $this->createFieldArgs($name, $typeName),
                'resolve' => function() {
                    return [
                        'string1',
                        'string2',
                        'string3',
                        'string4',
                        'string5',
                    ];
                }
            ];
        }
        return $fields;
    }

    protected function createFieldArgs($fieldName, $typeName)
    {
        return [
            'argString' => [
                'type' => Type::string()
            ],
            'argEnum' => [
                'type' => new EnumType([
                    'name' => $typeName . $fieldName . 'Enum',
                    'values' => [
                        "ONE", "TWO", "THREE"
                    ]
                ])
            ],
            'argInputObject' => [
                'type' => new InputObjectType([
                    'name' => $typeName . $fieldName . 'Input',
                    'fields' => [
                        'field1' => Type::string(),
                        'field2' => Type::int()
                    ]
                ])
            ]
        ];
    }

    public function resolveField($value, $args, $context, $resolveInfo)
    {
        return $resolveInfo->fieldName . '-value';
    }
}
