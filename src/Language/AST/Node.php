<?php
namespace GraphQL\Language\AST;

use GraphQL\Utils;

abstract class Node
{
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
            'start' => $this->loc->start,
            'end' => $this->loc->end
        ];
        return json_encode($tmp);
    }
}
