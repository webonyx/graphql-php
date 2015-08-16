<?php
namespace GraphQL\Language;

use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\SelectionSet;

class VisitorTest extends \PHPUnit_Framework_TestCase
{
    public function testAllowsForEditingOnEnter()
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]);
        $editedAst = Visitor::visit($ast, [
            'enter' => function($node) {
                if ($node instanceof Field && $node->name->value === 'b') {
                    return Visitor::removeNode();
                }
            }
        ]);

        $this->assertEquals(
            Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]),
            $ast
        );
        $this->assertEquals(
            Parser::parse('{ a,    c { a,    c } }', ['noLocation' => true]),
            $editedAst
        );
    }

    public function testAllowsForEditingOnLeave()
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]);
        $editedAst = Visitor::visit($ast, [
            'leave' => function($node) {
                if ($node instanceof Field && $node->name->value === 'b') {
                    return Visitor::removeNode();
                }
            }
        ]);

        $this->assertEquals(
            Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]),
            $ast
        );

        $this->assertEquals(
            Parser::parse('{ a,    c { a,    c } }', ['noLocation' => true]),
            $editedAst
        );
    }

    public function testVisitsEditedNode()
    {
        $addedField = new Field(array(
            'name' => new Name(array(
                'value' => '__typename'
            ))
        ));

        $didVisitAddedField = false;

        $ast = Parser::parse('{ a { x } }');

        Visitor::visit($ast, [
            'enter' => function($node) use ($addedField, &$didVisitAddedField) {
                if ($node instanceof Field && $node->name->value === 'a') {
                    return new Field([
                        'selectionSet' => new SelectionSet(array(
                            'selections' => array_merge([$addedField], $node->selectionSet->selections)
                        ))
                    ]);
                }
                if ($node === $addedField) {
                    $didVisitAddedField = true;
                }
            }
        ]);

        $this->assertTrue($didVisitAddedField);
    }

    public function testAllowsSkippingASubTree()
    {
        $visited = [];
        $ast = Parser::parse('{ a, b { x }, c }');

        Visitor::visit($ast, [
            'enter' => function(Node $node) use (&$visited) {
                $visited[] = ['enter', $node->kind, isset($node->value) ? $node->value : null];
                if ($node instanceof Field && $node->name->value === 'b') {
                    return Visitor::skipNode();
                }
            },
            'leave' => function (Node $node) use (&$visited) {
                $visited[] = ['leave', $node->kind, isset($node->value) ? $node->value : null];
            }
        ]);

        $expected = [
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'c' ],
            [ 'leave', 'Name', 'c' ],
            [ 'leave', 'Field', null ],
            [ 'leave', 'SelectionSet', null ],
            [ 'leave', 'OperationDefinition', null ],
            [ 'leave', 'Document', null ]
        ];

        $this->assertEquals($expected, $visited);
    }

    public function testAllowsEarlyExitWhileVisiting()
    {
        $visited = [];
        $ast = Parser::parse('{ a, b { x }, c }');

        Visitor::visit($ast, [
            'enter' => function(Node $node) use (&$visited) {
                $visited[] = ['enter', $node->kind, isset($node->value) ? $node->value : null];
                if ($node instanceof Name && $node->value === 'x') {
                    return Visitor::stop();
                }
            },
            'leave' => function(Node $node) use (&$visited) {
                $visited[] = ['leave', $node->kind, isset($node->value) ? $node->value : null];
            }
        ]);

        $expected = [
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'b' ],
            [ 'leave', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'x' ]
        ];

        $this->assertEquals($expected, $visited);
    }

    public function testAllowsANamedFunctionsVisitorAPI()
    {
        $visited = [];
        $ast = Parser::parse('{ a, b { x }, c }');

        Visitor::visit($ast, [
            Node::NAME => function(Name $node) use (&$visited) {
                $visited[] = ['enter', $node->kind, $node->value];
            },
            Node::SELECTION_SET => [
                'enter' => function(SelectionSet $node) use (&$visited) {
                    $visited[] = ['enter', $node->kind, null];
                },
                'leave' => function(SelectionSet $node) use (&$visited) {
                    $visited[] = ['leave', $node->kind, null];
                }
            ]
        ]);

        $expected = [
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'enter', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Name', 'x' ],
            [ 'leave', 'SelectionSet', null ],
            [ 'enter', 'Name', 'c' ],
            [ 'leave', 'SelectionSet', null ],
        ];

        $this->assertEquals($expected, $visited);
    }

    public function testVisitsKitchenSink()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $visited = [];
        Visitor::visit($ast, [
            'enter' => function(Node $node, $key, $parent) use (&$visited) {
                $r = ['enter', $node->kind, $key, $parent instanceof Node ? $parent->kind : null];
                $visited[] = $r;
            },
            'leave' => function(Node $node, $key, $parent) use (&$visited) {
                $r = ['leave', $node->kind, $key, $parent instanceof Node ? $parent->kind : null];
                $visited[] = $r;
            }
        ]);

        $expected = [
            [ 'enter', 'Document', null, null ],
            [ 'enter', 'OperationDefinition', 0, null ],
            [ 'enter', 'Name', 'name', 'OperationDefinition' ],
            [ 'leave', 'Name', 'name', 'OperationDefinition' ],
            [ 'enter', 'VariableDefinition', 0, null ],
            [ 'enter', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'leave', 'VariableDefinition', 0, null ],
            [ 'enter', 'VariableDefinition', 1, null ],
            [ 'enter', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'EnumValue', 'defaultValue', 'VariableDefinition' ],
            [ 'leave', 'EnumValue', 'defaultValue', 'VariableDefinition' ],
            [ 'leave', 'VariableDefinition', 1, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'alias', 'Field' ],
            [ 'leave', 'Name', 'alias', 'Field' ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'ListValue', 'value', 'Argument' ],
            [ 'enter', 'IntValue', 0, null ],
            [ 'leave', 'IntValue', 0, null ],
            [ 'enter', 'IntValue', 1, null ],
            [ 'leave', 'IntValue', 1, null ],
            [ 'leave', 'ListValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'InlineFragment', 1, null ],
            [ 'enter', 'NamedType', 'typeCondition', 'InlineFragment' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'typeCondition', 'InlineFragment' ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'Field', 1, null ],
            [ 'enter', 'Name', 'alias', 'Field' ],
            [ 'leave', 'Name', 'alias', 'Field' ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Argument', 1, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 1, null ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'FragmentSpread', 1, null ],
            [ 'enter', 'Name', 'name', 'FragmentSpread' ],
            [ 'leave', 'Name', 'name', 'FragmentSpread' ],
            [ 'leave', 'FragmentSpread', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'leave', 'InlineFragment', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 0, null ],
            [ 'enter', 'OperationDefinition', 1, null ],
            [ 'enter', 'Name', 'name', 'OperationDefinition' ],
            [ 'leave', 'Name', 'name', 'OperationDefinition' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 1, null ],
            [ 'enter', 'FragmentDefinition', 2, null ],
            [ 'enter', 'Name', 'name', 'FragmentDefinition' ],
            [ 'leave', 'Name', 'name', 'FragmentDefinition' ],
            [ 'enter', 'NamedType', 'typeCondition', 'FragmentDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'typeCondition', 'FragmentDefinition' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'FragmentDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Argument', 1, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 1, null ],
            [ 'enter', 'Argument', 2, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'ObjectValue', 'value', 'Argument' ],
            [ 'enter', 'ObjectField', 0, null ],
            [ 'enter', 'Name', 'name', 'ObjectField' ],
            [ 'leave', 'Name', 'name', 'ObjectField' ],
            [ 'enter', 'StringValue', 'value', 'ObjectField' ],
            [ 'leave', 'StringValue', 'value', 'ObjectField' ],
            [ 'leave', 'ObjectField', 0, null ],
            [ 'leave', 'ObjectValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 2, null ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'FragmentDefinition' ],
            [ 'leave', 'FragmentDefinition', 2, null ],
            [ 'enter', 'OperationDefinition', 3, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Argument', 1, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 1, null ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'Field', 1, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 3, null ],
            [ 'leave', 'Document', null, null ]
        ];

        $this->assertEquals($expected, $visited);
    }
}
