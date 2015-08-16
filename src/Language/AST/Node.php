<?php
namespace GraphQL\Language\AST;

use GraphQL\Utils;

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

    
    /**
        type Node = Name
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

    public $kind;

    /**
     * @var Location
     */
    public $loc;

    /**
     * @param array $vars
     */
    public function __construct(array $vars)
    {
        Utils::assign($this, $vars);
    }

    public function cloneDeep()
    {
        return $this->_cloneValue($this);
    }

    private function _cloneValue($value)
    {
        if (is_array($value)) {
            $cloned = [];
            foreach ($value as $key => $arrValue) {
                $cloned[$key] = $this->_cloneValue($arrValue);
            }
        } else if ($value instanceof Node) {
            $cloned = clone $value;
            foreach (get_object_vars($cloned) as $prop => $propValue) {
                $cloned->{$prop} = $this->_cloneValue($propValue);
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
        return json_encode($this);
    }
}
