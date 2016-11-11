<?php

namespace GraphQL\Language;

use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\DirectiveDefinition;
use GraphQL\Language\AST\EnumTypeDefinition;
use GraphQL\Language\AST\EnumValueDefinition;
use GraphQL\Language\AST\FieldDefinition;
use GraphQL\Language\AST\InputObjectTypeDefinition;
use GraphQL\Language\AST\InputValueDefinition;
use GraphQL\Language\AST\InterfaceTypeDefinition;
use GraphQL\Language\AST\ListValue;
use GraphQL\Language\AST\BooleanValue;
use GraphQL\Language\AST\Directive;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\EnumValue;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\FloatValue;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\ListType;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\NamedType;
use GraphQL\Language\AST\NonNullType;
use GraphQL\Language\AST\ObjectField;
use GraphQL\Language\AST\ObjectTypeDefinition;
use GraphQL\Language\AST\ObjectValue;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\OperationTypeDefinition;
use GraphQL\Language\AST\ScalarTypeDefinition;
use GraphQL\Language\AST\SchemaDefinition;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Language\AST\StringValue;
use GraphQL\Language\AST\TypeExtensionDefinition;
use GraphQL\Language\AST\TypeSystemDefinition;
use GraphQL\Language\AST\UnionTypeDefinition;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Error\SyntaxError;

class Parser
{
    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * Parser constructor.
     *
     * @param Lexer $lexer
     * @param array $options
     */
    function __construct(Lexer $lexer, array $options = [])
    {
        $this->lexer = $lexer;
        $this->lexer->setOptions($options);
    }

    /**
     * Available options:
     *
     * noLocation: boolean,
     * (By default, the parser creates AST nodes that know the location
     * in the source that they correspond to. This configuration flag
     * disables that behavior for performance or testing.)
     *
     * @param Source|string $source
     *
     * @return Document
     */
    public function parse($source)
    {
        $source = $source instanceof Source ? $source : new Source($source);

        $this->lexer->setSource($source);

        return $this->parseDocument();
    }


    /**
     * Given a string containing a GraphQL value (ex. `[42]`), parse the AST for
     * that value.
     * Throws GraphQLError if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Values directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: valueFromAST().
     *
     * @param Source|string $source
     *
     * @return BooleanValue|EnumValue|FloatValue|IntValue|ListValue|ObjectValue|StringValue|Variable
     */
    public function parseValue($source)
    {
        $source = $source instanceof Source ? $source : new Source($source);
        
        $this->lexer->setSource($source);

        $this->expect(Token::SOF);
        $value = $this->parseValueLiteral(false);
        $this->expect(Token::EOF);

        return $value;
    }

    /**
     * Given a string containing a GraphQL Type (ex. `[Int!]`), parse the AST for
     * that type.
     * Throws GraphQLError if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Types directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: typeFromAST().
     * @param Source|string $source
     *
     * @return ListType|Name|NonNullType
     */
    public function parseType($source)
    {
        $source = $source instanceof Source ? $source : new Source($source);

        $this->lexer->setSource($source);

        $this->expect(Token::SOF);
        $type = $this->parseTypeReference();
        $this->expect(Token::EOF);

        return $type;
    }

    /**
     * Returns a location object, used to identify the place in
     * the source that created a given parsed object.
     *
     * @param Token $startToken
     * @return Location|null
     */
    function loc(Token $startToken)
    {
        if (empty($this->lexer->getOptions()['noLocation'])) {
            return new Location($startToken, $this->lexer->lastToken, $this->lexer->getSource());
        }

        return null;
    }

    /**
     * Determines if the next token is of a given kind
     *
     * @param $kind
     * @return bool
     */
    function peek($kind)
    {
        return $this->lexer->token->getKind() === $kind;
    }

    /**
     * If the next token is of the given kind, return true after advancing
     * the parser. Otherwise, do not change the parser state and return false.
     *
     * @param $kind
     * @return bool
     */
    function skip($kind)
    {
        $match = $this->lexer->token->getKind() === $kind;

        if ($match) {
            $this->lexer->advance();
        }
        return $match;
    }

    /**
     * If the next token is of the given kind, return that token after advancing
     * the parser. Otherwise, do not change the parser state and return false.
     * @param string $kind
     * @return Token
     * @throws SyntaxError
     */
    function expect($kind)
    {
        $token = $this->lexer->token;

        if ($token->getKind() === $kind) {
            $this->lexer->advance();
            return $token;
        }

        throw new SyntaxError(
            $this->lexer->getSource(),
            $token->getStart(),
            "Expected $kind, found " . $token->getDescription()
        );
    }

    /**
     * If the next token is a keyword with the given value, return that token after
     * advancing the parser. Otherwise, do not change the parser state and return
     * false.
     *
     * @param string $value
     * @return Token
     * @throws SyntaxError
     */
    function expectKeyword($value)
    {
        $token = $this->lexer->token;

        if ($token->getKind() === Token::NAME && $token->value === $value) {
            $this->lexer->advance();
            return $token;
        }
        throw new SyntaxError(
            $this->lexer->getSource(),
            $token->getStart(),
            'Expected "' . $value . '", found ' . $token->getDescription()
        );
    }

    /**
     * @param Token|null $atToken
     * @return SyntaxError
     */
    function unexpected(Token $atToken = null)
    {
        $token = $atToken ?: $this->lexer->token;
        return new SyntaxError($this->lexer->getSource(), $token->getStart(), "Unexpected " . $token->getDescription());
    }

    /**
     * Returns a possibly empty list of parse nodes, determined by
     * the parseFn. This list begins with a lex token of openKind
     * and ends with a lex token of closeKind. Advances the parser
     * to the next lex token after the closing token.
     *
     * @param int $openKind
     * @param callable $parseFn
     * @param int $closeKind
     * @return array
     * @throws SyntaxError
     */
    function any($openKind, $parseFn, $closeKind)
    {
        $this->expect($openKind);

        $nodes = [];
        while (!$this->skip($closeKind)) {
            $nodes[] = $parseFn($this);
        }
        return $nodes;
    }

    /**
     * Returns a non-empty list of parse nodes, determined by
     * the parseFn. This list begins with a lex token of openKind
     * and ends with a lex token of closeKind. Advances the parser
     * to the next lex token after the closing token.
     *
     * @param $openKind
     * @param $parseFn
     * @param $closeKind
     * @return array
     * @throws SyntaxError
     */
    function many($openKind, $parseFn, $closeKind)
    {
        $this->expect($openKind);

        $nodes = [$parseFn($this)];
        while (!$this->skip($closeKind)) {
            $nodes[] = $parseFn($this);
        }
        return $nodes;
    }

    /**
     * Converts a name lex token into a name parse node.
     *
     * @return Name
     * @throws SyntaxError
     */
    function parseName()
    {
        $token = $this->expect(Token::NAME);

        return new Name($token->value, $this->loc($token));
    }

    /**
     * Implements the parsing rules in the Document section.
     *
     * @return Document
     * @throws SyntaxError
     */
    function parseDocument()
    {
        $start = $this->lexer->token;
        $this->expect(Token::SOF);

        $definitions = [];
        do {
            $definitions[] = $this->parseDefinition();
        } while (!$this->skip(Token::EOF));

        return new Document($definitions, $this->loc($start));
    }

    /**
     * @return OperationDefinition|FragmentDefinition|TypeSystemDefinition
     * @throws SyntaxError
     */
    function parseDefinition()
    {
        if ($this->peek(Token::BRACE_L)) {
            return $this->parseOperationDefinition();
        }

        if ($this->peek(Token::NAME)) {
            switch ($this->lexer->token->value) {
                case 'query':
                case 'mutation':
                case 'subscription':
                    return $this->parseOperationDefinition();

                case 'fragment':
                    return $this->parseFragmentDefinition();

                // Note: the Type System IDL is an experimental non-spec addition.
                case 'schema':
                case 'scalar':
                case 'type':
                case 'interface':
                case 'union':
                case 'enum':
                case 'input':
                case 'extend':
                case 'directive':
                    return $this->parseTypeSystemDefinition();
            }
        }

        throw $this->unexpected();
    }

    // Implements the parsing rules in the Operations section.

    /**
     * @return OperationDefinition
     * @throws SyntaxError
     */
    function parseOperationDefinition()
    {
        $start = $this->lexer->token;
        if ($this->peek(Token::BRACE_L)) {
            return new OperationDefinition(
                null,
                'query',
                null,
                [],
                $this->parseSelectionSet(),
                $this->loc($start)
            );
        }

        $operation = $this->parseOperationType();

        $name = null;
        if ($this->peek(Token::NAME)) {
            $name = $this->parseName();
        }

        return new OperationDefinition(
            $name,
            $operation,
            $this->parseVariableDefinitions(),
            $this->parseDirectives(),
            $this->parseSelectionSet(),
            $this->loc($start)
        );
    }

    /**
     * @return string
     * @throws SyntaxError
     */
    function parseOperationType()
    {
        $operationToken = $this->expect(Token::NAME);
        switch ($operationToken->value) {
            case 'query': return 'query';
            case 'mutation': return 'mutation';
            // Note: subscription is an experimental non-spec addition.
            case 'subscription': return 'subscription';
        }

        throw $this->unexpected($operationToken);
    }

    /**
     * @return array<VariableDefinition>
     */
    function parseVariableDefinitions()
    {
      return $this->peek(Token::PAREN_L) ?
        $this->many(
          Token::PAREN_L,
          [$this, 'parseVariableDefinition'],
          Token::PAREN_R
        ) :
        [];
    }

    /**
     * @return VariableDefinition
     * @throws SyntaxError
     */
    function parseVariableDefinition()
    {
        $start = $this->lexer->token;
        $var = $this->parseVariable();

        $this->expect(Token::COLON);
        $type = $this->parseTypeReference();

        return new VariableDefinition(
            $var,
            $type,
            $this->skip(Token::EQUALS) ? $this->parseValueLiteral(true) : null,
            $this->loc($start)
        );
    }

    /**
     * @return Variable
     * @throws SyntaxError
     */
    function parseVariable()
    {
        $start = $this->lexer->token;
        $this->expect(Token::DOLLAR);

        return new Variable(
            $this->parseName(),
            $this->loc($start)
        );
    }

    /**
     * @return SelectionSet
     */
    function parseSelectionSet()
    {
        $start = $this->lexer->token;
        return new SelectionSet(
            $this->many(Token::BRACE_L, [$this, 'parseSelection'], Token::BRACE_R),
            $this->loc($start)
        );
    }

    /**
     *  Selection :
     *   - Field
     *   - FragmentSpread
     *   - InlineFragment
     *
     * @return mixed
     */
    function parseSelection()
    {
        return $this->peek(Token::SPREAD) ?
            $this->parseFragment() :
            $this->parseField();
    }

    /**
     * @return Field
     */
    function parseField()
    {
        $start = $this->lexer->token;
        $nameOrAlias = $this->parseName();

        if ($this->skip(Token::COLON)) {
            $alias = $nameOrAlias;
            $name = $this->parseName();
        } else {
            $alias = null;
            $name = $nameOrAlias;
        }

        return new Field(
            $name,
            $alias,
            $this->parseArguments(),
            $this->parseDirectives(),
            $this->peek(Token::BRACE_L) ? $this->parseSelectionSet() : null,
            $this->loc($start)
        );
    }

    /**
     * @return array<Argument>
     */
    function parseArguments()
    {
        return $this->peek(Token::PAREN_L) ?
            $this->many(Token::PAREN_L, [$this, 'parseArgument'], Token::PAREN_R) :
            [];
    }

    /**
     * @return Argument
     * @throws SyntaxError
     */
    function parseArgument()
    {
        $start = $this->lexer->token;
        $name = $this->parseName();

        $this->expect(Token::COLON);
        $value = $this->parseValueLiteral(false);

        return new Argument($name, $value, $this->loc($start));
    }

    // Implements the parsing rules in the Fragments section.

    /**
     * @return FragmentSpread|InlineFragment
     * @throws SyntaxError
     */
    function parseFragment()
    {
        $start = $this->lexer->token;
        $this->expect(Token::SPREAD);

        if ($this->peek(Token::NAME) && $this->lexer->token->value !== 'on') {
            return new FragmentSpread(
                $this->parseFragmentName(),
                $this->parseDirectives(),
                $this->loc($start)
            );
        }

        $typeCondition = null;
        if ($this->lexer->token->value === 'on') {
            $this->lexer->advance();
            $typeCondition = $this->parseNamedType();
        }

        return new InlineFragment(
            $typeCondition,
            $this->parseDirectives(),
            $this->parseSelectionSet(),
            $this->loc($start)
        );
    }

    /**
     * @return FragmentDefinition
     * @throws SyntaxError
     */
    function parseFragmentDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('fragment');

        $name = $this->parseFragmentName();
        $this->expectKeyword('on');
        $typeCondition = $this->parseNamedType();

        return new FragmentDefinition(
            $name,
            $typeCondition,
            $this->parseDirectives(),
            $this->parseSelectionSet(),
            $this->loc($start)
        );
    }

    /**
     * @return Name
     * @throws SyntaxError
     */
    function parseFragmentName()
    {
        if ($this->lexer->token->value === 'on') {
            throw $this->unexpected();
        }
        return $this->parseName();
    }

    // Implements the parsing rules in the Values section.

    /**
     * Value[Const] :
     *   - [~Const] Variable
     *   - IntValue
     *   - FloatValue
     *   - StringValue
     *   - BooleanValue
     *   - EnumValue
     *   - ListValue[?Const]
     *   - ObjectValue[?Const]
     *
     * BooleanValue : one of `true` `false`
     *
     * EnumValue : Name but not `true`, `false` or `null`
     *
     * @param $isConst
     * @return BooleanValue|EnumValue|FloatValue|IntValue|StringValue|Variable|ListValue|ObjectValue
     * @throws SyntaxError
     */
    function parseValueLiteral($isConst)
    {
        $token = $this->lexer->token;
        switch ($token->getKind()) {
            case Token::BRACKET_L:
                return $this->parseArray($isConst);
            case Token::BRACE_L:
                return $this->parseObject($isConst);
            case Token::INT:
                $this->lexer->advance();
                return new IntValue(
                    $token->value,
                    $this->loc($token)
                );
            case Token::FLOAT:
                $this->lexer->advance();
                return new FloatValue(
                    $token->value,
                    $this->loc($token)
                );
            case Token::STRING:
                $this->lexer->advance();
                return new StringValue(
                    $token->value,
                    $this->loc($token)
                );
            case Token::NAME:
                if ($token->value === 'true' || $token->value === 'false') {
                    $this->lexer->advance();
                    return new BooleanValue($token->value === 'true', $this->loc($token));
                } else if ($token->value !== 'null') {
                    $this->lexer->advance();
                    return new EnumValue(
                        $token->value,
                        $this->loc($token)
                    );
                }
                break;

            case Token::DOLLAR:
                if (!$isConst) {
                    return $this->parseVariable();
                }
                break;
        }
        throw $this->unexpected();
    }

    /**
     * @return BooleanValue|EnumValue|FloatValue|IntValue|StringValue|Variable
     * @throws SyntaxError
     */
    function parseConstValue()
    {
        return $this->parseValueLiteral(true);
    }

    /**
     * @return BooleanValue|EnumValue|FloatValue|IntValue|ListValue|ObjectValue|StringValue|Variable
     */
    function parseVariableValue()
    {
        return $this->parseValueLiteral(false);
    }

    /**
     * @param bool $isConst
     * @return ListValue
     */
    function parseArray($isConst)
    {
        $start = $this->lexer->token;
        $item = $isConst ? 'parseConstValue' : 'parseVariableValue';
        return new ListValue(
            $this->any(Token::BRACKET_L, [$this, $item], Token::BRACKET_R),
            $this->loc($start)
        );
    }

    /**
     * @param $isConst
     * @return ObjectValue
     */
    function parseObject($isConst)
    {
        $start = $this->lexer->token;
        $this->expect(Token::BRACE_L);
        $fields = [];
        while (!$this->skip(Token::BRACE_R)) {
            $fields[] = $this->parseObjectField($isConst);
        }
        return new ObjectValue(
            $fields,
            $this->loc($start)
        );
    }

    /**
     * @param $isConst
     * @return ObjectField
     */
    function parseObjectField($isConst)
    {
        $start = $this->lexer->token;
        $name = $this->parseName();

        $this->expect(Token::COLON);

        return new ObjectField(
            $name,
            $this->parseValueLiteral($isConst),
            $this->loc($start)
        );
    }

    // Implements the parsing rules in the Directives section.

    /**
     * @return array<Directive>
     */
    function parseDirectives()
    {
        $directives = [];
        while ($this->peek(Token::AT)) {
            $directives[] = $this->parseDirective();
        }
        return $directives;
    }

    /**
     * @return Directive
     * @throws SyntaxError
     */
    function parseDirective()
    {
        $start = $this->lexer->token;
        $this->expect(Token::AT);
        return new Directive(
            $this->parseName(),
            $this->parseArguments(),
            $this->loc($start)
        );
    }

    // Implements the parsing rules in the Types section.

    /**
     * Handles the Type: TypeName, ListType, and NonNullType parsing rules.
     *
     * @return ListType|Name|NonNullType
     * @throws SyntaxError
     */
    function parseTypeReference()
    {
        $start = $this->lexer->token;

        if ($this->skip(Token::BRACKET_L)) {
            $type = $this->parseTypeReference();
            $this->expect(Token::BRACKET_R);
            $type = new ListType(
                $type,
                $this->loc($start)
            );
        } else {
            $type = $this->parseNamedType();
        }
        if ($this->skip(Token::BANG)) {
            return new NonNullType(
                $type,
                $this->loc($start)
            );

        }
        return $type;
    }

    function parseNamedType()
    {
        $start = $this->lexer->token;

        return new NamedType($this->parseName(), $this->loc($start));
    }

    // Implements the parsing rules in the Type Definition section.

    /**
     * TypeSystemDefinition :
     *   - SchemaDefinition
     *   - TypeDefinition
     *   - TypeExtensionDefinition
     *   - DirectiveDefinition
     *
     * TypeDefinition :
     *   - ScalarTypeDefinition
     *   - ObjectTypeDefinition
     *   - InterfaceTypeDefinition
     *   - UnionTypeDefinition
     *   - EnumTypeDefinition
     *   - InputObjectTypeDefinition
     *
     * @return TypeSystemDefinition
     * @throws SyntaxError
     */
    function parseTypeSystemDefinition()
    {
        if ($this->peek(Token::NAME)) {
            switch ($this->lexer->token->value) {
                case 'schema': return $this->parseSchemaDefinition();
                case 'scalar': return $this->parseScalarTypeDefinition();
                case 'type': return $this->parseObjectTypeDefinition();
                case 'interface': return $this->parseInterfaceTypeDefinition();
                case 'union': return $this->parseUnionTypeDefinition();
                case 'enum': return $this->parseEnumTypeDefinition();
                case 'input': return $this->parseInputObjectTypeDefinition();
                case 'extend': return $this->parseTypeExtensionDefinition();
                case 'directive': return $this->parseDirectiveDefinition();
            }
        }

        throw $this->unexpected();
    }

    /**
     * @return SchemaDefinition
     * @throws SyntaxError
     */
    function parseSchemaDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('schema');
        $directives = $this->parseDirectives();

        $operationTypes = $this->many(
            Token::BRACE_L,
            [$this, 'parseOperationTypeDefinition'],
            Token::BRACE_R
        );

        return new SchemaDefinition(
            $directives,
            $operationTypes,
            $this->loc($start)
        );
    }

    /**
     * @return OperationTypeDefinition
     */
    function parseOperationTypeDefinition()
    {
        $start = $this->lexer->token;
        $operation = $this->parseOperationType();
        $this->expect(Token::COLON);
        $type = $this->parseNamedType();

        return new OperationTypeDefinition(
            $operation,
            $type,
            $this->loc($start)
        );
    }

    /**
     * @return ScalarTypeDefinition
     * @throws SyntaxError
     */
    function parseScalarTypeDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('scalar');
        $name = $this->parseName();
        $directives = $this->parseDirectives();

        return new ScalarTypeDefinition(
            $name,
            $directives,
            $this->loc($start)
        );
    }

    /**
     * @return ObjectTypeDefinition
     * @throws SyntaxError
     */
    function parseObjectTypeDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('type');
        $name = $this->parseName();
        $interfaces = $this->parseImplementsInterfaces();
        $directives = $this->parseDirectives();

        $fields = $this->any(
            Token::BRACE_L,
            [$this, 'parseFieldDefinition'],
            Token::BRACE_R
        );

        return new ObjectTypeDefinition(
            $name,
            $interfaces,
            $directives,
            $fields,
            $this->loc($start)
        );
    }

    /**
     * @return NamedType[]
     */
    function parseImplementsInterfaces()
    {
        $types = [];
        if ($this->lexer->token->value === 'implements') {
            $this->lexer->advance();
            do {
                $types[] = $this->parseNamedType();
            } while ($this->peek(Token::NAME));
        }
        return $types;
    }

    /**
     * @return FieldDefinition
     * @throws SyntaxError
     */
    function parseFieldDefinition()
    {
        $start = $this->lexer->token;
        $name = $this->parseName();
        $args = $this->parseArgumentDefs();
        $this->expect(Token::COLON);
        $type = $this->parseTypeReference();
        $directives = $this->parseDirectives();

        return new FieldDefinition(
            $name,
            $args,
            $type,
            $directives,
            $this->loc($start)
        );
    }

    /**
     * @return InputValueDefinition[]
     */
    function parseArgumentDefs()
    {
        if (!$this->peek(Token::PAREN_L)) {
            return [];
        }
        return $this->many(Token::PAREN_L, [$this, 'parseInputValueDef'], Token::PAREN_R);
    }

    /**
     * @return InputValueDefinition
     * @throws SyntaxError
     */
    function parseInputValueDef()
    {
        $start = $this->lexer->token;
        $name = $this->parseName();
        $this->expect(Token::COLON);
        $type = $this->parseTypeReference();
        $defaultValue = null;
        if ($this->skip(Token::EQUALS)) {
            $defaultValue = $this->parseConstValue();
        }
        $directives = $this->parseDirectives();
        return new InputValueDefinition(
            $name,
            $type,
            $defaultValue,
            $directives,
            $this->loc($start)
        );
    }

    /**
     * @return InterfaceTypeDefinition
     * @throws SyntaxError
     */
    function parseInterfaceTypeDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('interface');
        $name = $this->parseName();
        $directives = $this->parseDirectives();
        $fields = $this->any(
            Token::BRACE_L,
            [$this, 'parseFieldDefinition'],
            Token::BRACE_R
        );

        return new InterfaceTypeDefinition(
            $name,
            $directives,
            $fields,
            $this->loc($start)
        );
    }

    /**
     * @return UnionTypeDefinition
     * @throws SyntaxError
     */
    function parseUnionTypeDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('union');
        $name = $this->parseName();
        $directives = $this->parseDirectives();
        $this->expect(Token::EQUALS);
        $types = $this->parseUnionMembers();

        return new UnionTypeDefinition(
            $name,
            $directives,
            $types,
            $this->loc($start)
        );
    }

    /**
     * UnionMembers :
     *   - NamedType
     *   - UnionMembers | NamedType
     *
     * @return NamedType[]
     */
    function parseUnionMembers()
    {
        $members = [];
        do {
            $members[] = $this->parseNamedType();
        } while ($this->skip(Token::PIPE));
        return $members;
    }

    /**
     * @return EnumTypeDefinition
     * @throws SyntaxError
     */
    function parseEnumTypeDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('enum');
        $name = $this->parseName();
        $directives = $this->parseDirectives();
        $values = $this->many(
            Token::BRACE_L,
            [$this, 'parseEnumValueDefinition'],
            Token::BRACE_R
        );

        return new EnumTypeDefinition(
            $name,
            $directives,
            $values,
            $this->loc($start)
        );
    }

    /**
     * @return EnumValueDefinition
     */
    function parseEnumValueDefinition()
    {
        $start = $this->lexer->token;
        $name = $this->parseName();
        $directives = $this->parseDirectives();

        return new EnumValueDefinition(
            $name,
            $directives,
            $this->loc($start)
        );
    }

    /**
     * @return InputObjectTypeDefinition
     * @throws SyntaxError
     */
    function parseInputObjectTypeDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('input');
        $name = $this->parseName();
        $directives = $this->parseDirectives();
        $fields = $this->any(
            Token::BRACE_L,
            [$this, 'parseInputValueDef'],
            Token::BRACE_R
        );

        return new InputObjectTypeDefinition(
            $name,
            $directives,
            $fields,
            $this->loc($start)
        );
    }

    /**
     * @return TypeExtensionDefinition
     * @throws SyntaxError
     */
    function parseTypeExtensionDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $definition = $this->parseObjectTypeDefinition();

        return new TypeExtensionDefinition(
            $definition,
            $this->loc($start)
        );
    }

    /**
     * DirectiveDefinition :
     *   - directive @ Name ArgumentsDefinition? on DirectiveLocations
     *
     * @return DirectiveDefinition
     * @throws SyntaxError
     */
    function parseDirectiveDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('directive');
        $this->expect(Token::AT);
        $name = $this->parseName();
        $args = $this->parseArgumentDefs();
        $this->expectKeyword('on');
        $locations = $this->parseDirectiveLocations();

        return new DirectiveDefinition(
            $name,
            $args,
            $locations,
            $this->loc($start)
        );
    }

    /**
     * @return Name[]
     */
    function parseDirectiveLocations()
    {
        $locations = [];
        do {
            $locations[] = $this->parseName();
        } while ($this->skip(Token::PIPE));
        return $locations;
    }
}
