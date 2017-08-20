<?php
namespace GraphQL\Type\Definition;

/**
 * List of available directive locations
 */
class DirectiveLocation
{
    const IFACE = 'INTERFACE';
    const SUBSCRIPTION = 'SUBSCRIPTION';
    const FRAGMENT_SPREAD = 'FRAGMENT_SPREAD';
    const QUERY = 'QUERY';
    const MUTATION = 'MUTATION';
    const FRAGMENT_DEFINITION = 'FRAGMENT_DEFINITION';
    const INPUT_OBJECT = 'INPUT_OBJECT';
    const INLINE_FRAGMENT = 'INLINE_FRAGMENT';
    const UNION = 'UNION';
    const SCALAR = 'SCALAR';
    const FIELD_DEFINITION = 'FIELD_DEFINITION';
    const ARGUMENT_DEFINITION = 'ARGUMENT_DEFINITION';
    const ENUM = 'ENUM';
    const OBJECT = 'OBJECT';
    const ENUM_VALUE = 'ENUM_VALUE';
    const FIELD = 'FIELD';
    const SCHEMA = 'SCHEMA';
    const INPUT_FIELD_DEFINITION = 'INPUT_FIELD_DEFINITION';
}
