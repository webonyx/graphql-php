<?php

namespace GraphQL\Language\AST;

use GraphQL\Utils;

/**
 * Class Node
 * @package GraphQL\Language\AST
 */
abstract class Node
{
    // constants from language/kinds.js:

    const NAME = 'Name';

    // Document

    const DOCUMENT = 'Document';
    const OPERATION_DEFINITION = 'OperationDefinition';
    const VARIABLE_DEFINITION = 'VariableDefinition';
    const VARIABLE = 'Variable';
    const SELECTION_SET = 'SelectionSet';
    const FIELD = 'Field';
    const ARGUMENT = 'Argument';

    // Fragments

    const FRAGMENT_SPREAD = 'FragmentSpread';
    const INLINE_FRAGMENT = 'InlineFragment';
    const FRAGMENT_DEFINITION = 'FragmentDefinition';

    // Values

    const INT = 'IntValue';
    const FLOAT = 'FloatValue';
    const STRING = 'StringValue';
    const BOOLEAN = 'BooleanValue';
    const ENUM = 'EnumValue';
    const LST = 'ListValue';
    const OBJECT = 'ObjectValue';
    const OBJECT_FIELD = 'ObjectField';

    // Directives

    const DIRECTIVE = 'Directive';

    // Types

    const NAMED_TYPE = 'NamedType';
    const LIST_TYPE = 'ListType';
    const NON_NULL_TYPE = 'NonNullType';

    // Type System Definitions

    const SCHEMA_DEFINITION = 'SchemaDefinition';
    const OPERATION_TYPE_DEFINITION = 'OperationTypeDefinition';

    // Type Definitions

    const SCALAR_TYPE_DEFINITION = 'ScalarTypeDefinition';
    const OBJECT_TYPE_DEFINITION = 'ObjectTypeDefinition';
    const FIELD_DEFINITION = 'FieldDefinition';
    const INPUT_VALUE_DEFINITION = 'InputValueDefinition';
    const INTERFACE_TYPE_DEFINITION = 'InterfaceTypeDefinition';
    const UNION_TYPE_DEFINITION = 'UnionTypeDefinition';
    const ENUM_TYPE_DEFINITION = 'EnumTypeDefinition';
    const ENUM_VALUE_DEFINITION = 'EnumValueDefinition';
    const INPUT_OBJECT_TYPE_DEFINITION = 'InputObjectTypeDefinition';

    // Type Extensions

    const TYPE_EXTENSION_DEFINITION = 'TypeExtensionDefinition';

    // Directive Definitions

    const DIRECTIVE_DEFINITION = 'DirectiveDefinition';

    /**
    |  type Node = Name
    | Document
    | OperationDefinition
    | VariableDefinition
    | Variable
    | SelectionSet
    | Field
    | Argument
    | FragmentSpread
    | InlineFragment
    | FragmentDefinition
    | IntValue
    | FloatValue
    | StringValue
    | BooleanValue
    | EnumValue
    | ListValue
    | ObjectValue
    | ObjectField
    | Directive
    | ListType
    | NonNullType
     */

    protected $kind;

    /**
     * @var Location
     */
    protected $loc;

    /**
     * @param array $vars
     */
    public function __construct(array $vars)
    {
        Utils::assign($this, $vars);
    }

    /**
     * @return $this
     */
    public function cloneDeep()
    {
        return $this->cloneValue($this);
    }

    /**
     * @param $value
     * @return array|Node
     */
    private function cloneValue($value)
    {
        if (is_array($value)) {
            $cloned = [];
            foreach ($value as $key => $arrValue) {
                $cloned[$key] = $this->cloneValue($arrValue);
            }
        } else if ($value instanceof Node) {
            $cloned = clone $value;
            foreach (get_object_vars($cloned) as $prop => $propValue) {
                $cloned->{$prop} = $this->cloneValue($propValue);
            }
        } else {
            $cloned = $value;
        }

        return $cloned;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $tmp = (array) $this;
        $tmp['loc'] = [
            'start' => $this->getLoc()->start,
            'end' => $this->getLoc()->end
        ];
        return json_encode($tmp);
    }

    /**
     * @return mixed
     */
    public function getKind()
    {
        return $this->kind;
    }

    /**
     * @param mixed $kind
     *
     * @return Node
     */
    public function setKind($kind)
    {
        $this->kind = $kind;

        return $this;
    }

    /**
     * @return Location
     */
    public function getLoc()
    {
        return $this->loc;
    }

    /**
     * @param Location $loc
     *
     * @return Node
     */
    public function setLoc($loc)
    {
        $this->loc = $loc;

        return $this;
    }
}
