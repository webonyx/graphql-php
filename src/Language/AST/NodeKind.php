<?php

namespace GraphQL\Language\AST;

class NodeKind
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
    const NULL = 'NullValue';
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

    const SCALAR_TYPE_EXTENSION = 'ScalarTypeExtension';
    const OBJECT_TYPE_EXTENSION = 'ObjectTypeExtension';
    const INTERFACE_TYPE_EXTENSION = 'InterfaceTypeExtension';
    const UNION_TYPE_EXTENSION = 'UnionTypeExtension';
    const ENUM_TYPE_EXTENSION = 'EnumTypeExtension';
    const INPUT_OBJECT_TYPE_EXTENSION = 'InputObjectTypeExtension';

    // Directive Definitions

    const DIRECTIVE_DEFINITION = 'DirectiveDefinition';

    /**
     * @todo conver to const array when moving to PHP5.6
     * @var array
     */
    public static $classMap = [
        NodeKind::NAME => NameNode::class,

        // Document
        NodeKind::DOCUMENT => DocumentNode::class,
        NodeKind::OPERATION_DEFINITION => OperationDefinitionNode::class,
        NodeKind::VARIABLE_DEFINITION => VariableDefinitionNode::class,
        NodeKind::VARIABLE => VariableNode::class,
        NodeKind::SELECTION_SET => SelectionSetNode::class,
        NodeKind::FIELD => FieldNode::class,
        NodeKind::ARGUMENT => ArgumentNode::class,

        // Fragments
        NodeKind::FRAGMENT_SPREAD => FragmentSpreadNode::class,
        NodeKind::INLINE_FRAGMENT => InlineFragmentNode::class,
        NodeKind::FRAGMENT_DEFINITION => FragmentDefinitionNode::class,

        // Values
        NodeKind::INT => IntValueNode::class,
        NodeKind::FLOAT => FloatValueNode::class,
        NodeKind::STRING => StringValueNode::class,
        NodeKind::BOOLEAN => BooleanValueNode::class,
        NodeKind::ENUM => EnumValueNode::class,
        NodeKind::NULL => NullValueNode::class,
        NodeKind::LST => ListValueNode::class,
        NodeKind::OBJECT => ObjectValueNode::class,
        NodeKind::OBJECT_FIELD => ObjectFieldNode::class,

        // Directives
        NodeKind::DIRECTIVE => DirectiveNode::class,

        // Types
        NodeKind::NAMED_TYPE => NamedTypeNode::class,
        NodeKind::LIST_TYPE => ListTypeNode::class,
        NodeKind::NON_NULL_TYPE => NonNullTypeNode::class,

        // Type System Definitions
        NodeKind::SCHEMA_DEFINITION => SchemaDefinitionNode::class,
        NodeKind::OPERATION_TYPE_DEFINITION => OperationTypeDefinitionNode::class,

        // Type Definitions
        NodeKind::SCALAR_TYPE_DEFINITION => ScalarTypeDefinitionNode::class,
        NodeKind::OBJECT_TYPE_DEFINITION => ObjectTypeDefinitionNode::class,
        NodeKind::FIELD_DEFINITION => FieldDefinitionNode::class,
        NodeKind::INPUT_VALUE_DEFINITION => InputValueDefinitionNode::class,
        NodeKind::INTERFACE_TYPE_DEFINITION => InterfaceTypeDefinitionNode::class,
        NodeKind::UNION_TYPE_DEFINITION => UnionTypeDefinitionNode::class,
        NodeKind::ENUM_TYPE_DEFINITION => EnumTypeDefinitionNode::class,
        NodeKind::ENUM_VALUE_DEFINITION => EnumValueDefinitionNode::class,
        NodeKind::INPUT_OBJECT_TYPE_DEFINITION =>InputObjectTypeDefinitionNode::class,

        // Type Extensions
        NodeKind::SCALAR_TYPE_EXTENSION => ScalarTypeExtensionNode::class,
        NodeKind::OBJECT_TYPE_EXTENSION => ObjectTypeExtensionNode::class,
        NodeKind::INTERFACE_TYPE_EXTENSION => InterfaceTypeExtensionNode::class,
        NodeKind::UNION_TYPE_EXTENSION => UnionTypeExtensionNode::class,
        NodeKind::ENUM_TYPE_EXTENSION => EnumTypeExtensionNode::class,
        NodeKind::INPUT_OBJECT_TYPE_EXTENSION => InputObjectTypeExtensionNode::class,

        // Directive Definitions
        NodeKind::DIRECTIVE_DEFINITION => DirectiveDefinitionNode::class
    ];
}
