<?php
if (defined('GRAPHQL_WITH_DEPRECATED') && !GRAPHQL_WITH_DEPRECATED) {
    return ;
}

// Renamed as of 8.0:
class_alias('GraphQL\Error\FormattedError', 'GraphQL\FormattedError');
class_alias('GraphQL\Error\SyntaxError', 'GraphQL\SyntaxError');

class_alias('GraphQL\Language\AST\ArgumentNode', 'GraphQL\Language\AST\Argument');
class_alias('GraphQL\Language\AST\BooleanValueNode', 'GraphQL\Language\AST\BooleanValue');
class_alias('GraphQL\Language\AST\DefinitionNode', 'GraphQL\Language\AST\Definition');
class_alias('GraphQL\Language\AST\DirectiveNode', 'GraphQL\Language\AST\Directive');
class_alias('GraphQL\Language\AST\DocumentNode', 'GraphQL\Language\AST\Document');
class_alias('GraphQL\Language\AST\EnumValueNode', 'GraphQL\Language\AST\EnumValue');
class_alias('GraphQL\Language\AST\FieldNode', 'GraphQL\Language\AST\Field');
class_alias('GraphQL\Language\AST\FloatValueNode', 'GraphQL\Language\AST\FloatValue');
class_alias('GraphQL\Language\AST\FragmentDefinitionNode', 'GraphQL\Language\AST\FragmentDefinition');
class_alias('GraphQL\Language\AST\FragmentSpreadNode', 'GraphQL\Language\AST\FragmentSpread');
class_alias('GraphQL\Language\AST\InlineFragmentNode', 'GraphQL\Language\AST\InlineFragment');
class_alias('GraphQL\Language\AST\IntValueNode', 'GraphQL\Language\AST\IntValue');
class_alias('GraphQL\Language\AST\ListTypeNode', 'GraphQL\Language\AST\ListType');
class_alias('GraphQL\Language\AST\ListValueNode', 'GraphQL\Language\AST\ListValue');
class_alias('GraphQL\Language\AST\NamedTypeNode', 'GraphQL\Language\AST\NamedType');
class_alias('GraphQL\Language\AST\NameNode', 'GraphQL\Language\AST\Name');
class_alias('GraphQL\Language\AST\NonNullTypeNode', 'GraphQL\Language\AST\NonNullType');
class_alias('GraphQL\Language\AST\ObjectFieldNode', 'GraphQL\Language\AST\ObjectField');
class_alias('GraphQL\Language\AST\ObjectValueNode', 'GraphQL\Language\AST\ObjectValue');
class_alias('GraphQL\Language\AST\OperationDefinitionNode', 'GraphQL\Language\AST\OperationDefinition');
class_alias('GraphQL\Language\AST\SelectionNode', 'GraphQL\Language\AST\Selection');
class_alias('GraphQL\Language\AST\SelectionSetNode', 'GraphQL\Language\AST\SelectionSet');
class_alias('GraphQL\Language\AST\StringValueNode', 'GraphQL\Language\AST\StringValue');
class_alias('GraphQL\Language\AST\TypeNode', 'GraphQL\Language\AST\Type');
class_alias('GraphQL\Language\AST\ValueNode', 'GraphQL\Language\AST\Value');
class_alias('GraphQL\Language\AST\VariableDefinitionNode', 'GraphQL\Language\AST\VariableDefinition');
class_alias('GraphQL\Language\AST\VariableNode', 'GraphQL\Language\AST\Variable');
