<?php
namespace GraphQL\Language;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\OperationTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Error\SyntaxError;

/**
 * Parses string containing GraphQL query or [type definition](type-system/type-language.md) to Abstract Syntax Tree.
 */
class Parser
{
    /**
     * Given a GraphQL source, parses it into a `GraphQL\Language\AST\DocumentNode`.
     * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
     *
     * Available options:
     *
     * noLocation: boolean,
     * (By default, the parser creates AST nodes that know the location
     * in the source that they correspond to. This configuration flag
     * disables that behavior for performance or testing.)
     *
     * @api
     * @param Source|string $source
     * @param array $options
     * @return DocumentNode
     */
    public static function parse($source, array $options = [])
    {
        $sourceObj = $source instanceof Source ? $source : new Source($source);
        $parser = new self($sourceObj, $options);
        return $parser->parseDocument();
    }

    /**
     * Given a string containing a GraphQL value (ex. `[42]`), parse the AST for
     * that value.
     * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Values directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: `GraphQL\Utils\AST::valueFromAST()`.
     *
     * @api
     * @param Source|string $source
     * @param array $options
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode
     */
    public static function parseValue($source, array $options = [])
    {
        $sourceObj = $source instanceof Source ? $source : new Source($source);
        $parser = new Parser($sourceObj, $options);
        $parser->expect(Token::SOF);
        $value = $parser->parseValueLiteral(false);
        $parser->expect(Token::EOF);
        return $value;
    }

    /**
     * Given a string containing a GraphQL Type (ex. `[Int!]`), parse the AST for
     * that type.
     * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Types directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: `GraphQL\Utils\AST::typeFromAST()`.
     *
     * @api
     * @param Source|string $source
     * @param array $options
     * @return ListTypeNode|NameNode|NonNullTypeNode
     */
    public static function parseType($source, array $options = [])
    {
        $sourceObj = $source instanceof Source ? $source : new Source($source);
        $parser = new Parser($sourceObj, $options);
        $parser->expect(Token::SOF);
        $type = $parser->parseTypeReference();
        $parser->expect(Token::EOF);
        return $type;
    }

    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * Parser constructor.
     * @param Source $source
     * @param array $options
     */
    function __construct(Source $source, array $options = [])
    {
        $this->lexer = new Lexer($source, $options);
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
        if (empty($this->lexer->options['noLocation'])) {
            return new Location($startToken, $this->lexer->lastToken, $this->lexer->source);
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
        return $this->lexer->token->kind === $kind;
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
        $match = $this->lexer->token->kind === $kind;

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

        if ($token->kind === $kind) {
            $this->lexer->advance();
            return $token;
        }

        throw new SyntaxError(
            $this->lexer->source,
            $token->start,
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

        if ($token->kind === Token::NAME && $token->value === $value) {
            $this->lexer->advance();
            return $token;
        }
        throw new SyntaxError(
            $this->lexer->source,
            $token->start,
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
        return new SyntaxError($this->lexer->source, $token->start, "Unexpected " . $token->getDescription());
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
     * @return NodeList
     * @throws SyntaxError
     */
    function any($openKind, $parseFn, $closeKind)
    {
        $this->expect($openKind);

        $nodes = [];
        while (!$this->skip($closeKind)) {
            $nodes[] = $parseFn($this);
        }
        return new NodeList($nodes);
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
     * @return NodeList
     * @throws SyntaxError
     */
    function many($openKind, $parseFn, $closeKind)
    {
        $this->expect($openKind);

        $nodes = [$parseFn($this)];
        while (!$this->skip($closeKind)) {
            $nodes[] = $parseFn($this);
        }
        return new NodeList($nodes);
    }

    /**
     * Converts a name lex token into a name parse node.
     *
     * @return NameNode
     * @throws SyntaxError
     */
    function parseName()
    {
        $token = $this->expect(Token::NAME);

        return new NameNode([
            'value' => $token->value,
            'loc' => $this->loc($token)
        ]);
    }

    /**
     * Implements the parsing rules in the Document section.
     *
     * @return DocumentNode
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

        return new DocumentNode([
            'definitions' => new NodeList($definitions),
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @return OperationDefinitionNode|FragmentDefinitionNode|TypeSystemDefinitionNode
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
     * @return OperationDefinitionNode
     * @throws SyntaxError
     */
    function parseOperationDefinition()
    {
        $start = $this->lexer->token;
        if ($this->peek(Token::BRACE_L)) {
            return new OperationDefinitionNode([
                'operation' => 'query',
                'name' => null,
                'variableDefinitions' => null,
                'directives' => new NodeList([]),
                'selectionSet' => $this->parseSelectionSet(),
                'loc' => $this->loc($start)
            ]);
        }

        $operation = $this->parseOperationType();

        $name = null;
        if ($this->peek(Token::NAME)) {
            $name = $this->parseName();
        }

        return new OperationDefinitionNode([
            'operation' => $operation,
            'name' => $name,
            'variableDefinitions' => $this->parseVariableDefinitions(),
            'directives' => $this->parseDirectives(),
            'selectionSet' => $this->parseSelectionSet(),
            'loc' => $this->loc($start)
        ]);
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
     * @return VariableDefinitionNode[]|NodeList
     */
    function parseVariableDefinitions()
    {
        return $this->peek(Token::PAREN_L) ?
            $this->many(
                Token::PAREN_L,
                [$this, 'parseVariableDefinition'],
                Token::PAREN_R
            ) :
            new NodeList([]);
    }

    /**
     * @return VariableDefinitionNode
     * @throws SyntaxError
     */
    function parseVariableDefinition()
    {
        $start = $this->lexer->token;
        $var = $this->parseVariable();

        $this->expect(Token::COLON);
        $type = $this->parseTypeReference();

        return new VariableDefinitionNode([
            'variable' => $var,
            'type' => $type,
            'defaultValue' =>
                ($this->skip(Token::EQUALS) ? $this->parseValueLiteral(true) : null),
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @return VariableNode
     * @throws SyntaxError
     */
    function parseVariable()
    {
        $start = $this->lexer->token;
        $this->expect(Token::DOLLAR);

        return new VariableNode([
            'name' => $this->parseName(),
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @return SelectionSetNode
     */
    function parseSelectionSet()
    {
        $start = $this->lexer->token;
        return new SelectionSetNode([
            'selections' => $this->many(Token::BRACE_L, [$this, 'parseSelection'], Token::BRACE_R),
            'loc' => $this->loc($start)
        ]);
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
     * @return FieldNode
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

        return new FieldNode([
            'alias' => $alias,
            'name' => $name,
            'arguments' => $this->parseArguments(),
            'directives' => $this->parseDirectives(),
            'selectionSet' => $this->peek(Token::BRACE_L) ? $this->parseSelectionSet() : null,
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @return ArgumentNode[]|NodeList
     */
    function parseArguments()
    {
        return $this->peek(Token::PAREN_L) ?
            $this->many(Token::PAREN_L, [$this, 'parseArgument'], Token::PAREN_R) :
            new NodeList([]);
    }

    /**
     * @return ArgumentNode
     * @throws SyntaxError
     */
    function parseArgument()
    {
        $start = $this->lexer->token;
        $name = $this->parseName();

        $this->expect(Token::COLON);
        $value = $this->parseValueLiteral(false);

        return new ArgumentNode([
            'name' => $name,
            'value' => $value,
            'loc' => $this->loc($start)
        ]);
    }

    // Implements the parsing rules in the Fragments section.

    /**
     * @return FragmentSpreadNode|InlineFragmentNode
     * @throws SyntaxError
     */
    function parseFragment()
    {
        $start = $this->lexer->token;
        $this->expect(Token::SPREAD);

        if ($this->peek(Token::NAME) && $this->lexer->token->value !== 'on') {
            return new FragmentSpreadNode([
                'name' => $this->parseFragmentName(),
                'directives' => $this->parseDirectives(),
                'loc' => $this->loc($start)
            ]);
        }

        $typeCondition = null;
        if ($this->lexer->token->value === 'on') {
            $this->lexer->advance();
            $typeCondition = $this->parseNamedType();
        }

        return new InlineFragmentNode([
            'typeCondition' => $typeCondition,
            'directives' => $this->parseDirectives(),
            'selectionSet' => $this->parseSelectionSet(),
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @return FragmentDefinitionNode
     * @throws SyntaxError
     */
    function parseFragmentDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('fragment');

        $name = $this->parseFragmentName();
        $this->expectKeyword('on');
        $typeCondition = $this->parseNamedType();

        return new FragmentDefinitionNode([
            'name' => $name,
            'typeCondition' => $typeCondition,
            'directives' => $this->parseDirectives(),
            'selectionSet' => $this->parseSelectionSet(),
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @return NameNode
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
     *   - NullValue
     *   - EnumValue
     *   - ListValue[?Const]
     *   - ObjectValue[?Const]
     *
     * BooleanValue : one of `true` `false`
     *
     * NullValue : `null`
     *
     * EnumValue : Name but not `true`, `false` or `null`
     *
     * @param $isConst
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|StringValueNode|VariableNode|ListValueNode|ObjectValueNode|NullValueNode
     * @throws SyntaxError
     */
    function parseValueLiteral($isConst)
    {
        $token = $this->lexer->token;
        switch ($token->kind) {
            case Token::BRACKET_L:
                return $this->parseArray($isConst);
            case Token::BRACE_L:
                return $this->parseObject($isConst);
            case Token::INT:
                $this->lexer->advance();
                return new IntValueNode([
                    'value' => $token->value,
                    'loc' => $this->loc($token)
                ]);
            case Token::FLOAT:
                $this->lexer->advance();
                return new FloatValueNode([
                    'value' => $token->value,
                    'loc' => $this->loc($token)
                ]);
            case Token::STRING:
                $this->lexer->advance();
                return new StringValueNode([
                    'value' => $token->value,
                    'loc' => $this->loc($token)
                ]);
            case Token::NAME:
                if ($token->value === 'true' || $token->value === 'false') {
                    $this->lexer->advance();
                    return new BooleanValueNode([
                        'value' => $token->value === 'true',
                        'loc' => $this->loc($token)
                    ]);
                } else if ($token->value === 'null') {
                    $this->lexer->advance();
                    return new NullValueNode([
                        'loc' => $this->loc($token)
                    ]);
                } else {
                    $this->lexer->advance();
                    return new EnumValueNode([
                        'value' => $token->value,
                        'loc' => $this->loc($token)
                    ]);
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
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|StringValueNode|VariableNode
     * @throws SyntaxError
     */
    function parseConstValue()
    {
        return $this->parseValueLiteral(true);
    }

    /**
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode
     */
    function parseVariableValue()
    {
        return $this->parseValueLiteral(false);
    }

    /**
     * @param bool $isConst
     * @return ListValueNode
     */
    function parseArray($isConst)
    {
        $start = $this->lexer->token;
        $item = $isConst ? 'parseConstValue' : 'parseVariableValue';
        return new ListValueNode([
            'values' => $this->any(Token::BRACKET_L, [$this, $item], Token::BRACKET_R),
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @param $isConst
     * @return ObjectValueNode
     */
    function parseObject($isConst)
    {
        $start = $this->lexer->token;
        $this->expect(Token::BRACE_L);
        $fields = [];
        while (!$this->skip(Token::BRACE_R)) {
            $fields[] = $this->parseObjectField($isConst);
        }
        return new ObjectValueNode([
            'fields' => new NodeList($fields),
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @param $isConst
     * @return ObjectFieldNode
     */
    function parseObjectField($isConst)
    {
        $start = $this->lexer->token;
        $name = $this->parseName();

        $this->expect(Token::COLON);

        return new ObjectFieldNode([
            'name' => $name,
            'value' => $this->parseValueLiteral($isConst),
            'loc' => $this->loc($start)
        ]);
    }

    // Implements the parsing rules in the Directives section.

    /**
     * @return DirectiveNode[]|NodeList
     */
    function parseDirectives()
    {
        $directives = [];
        while ($this->peek(Token::AT)) {
            $directives[] = $this->parseDirective();
        }
        return new NodeList($directives);
    }

    /**
     * @return DirectiveNode
     * @throws SyntaxError
     */
    function parseDirective()
    {
        $start = $this->lexer->token;
        $this->expect(Token::AT);
        return new DirectiveNode([
            'name' => $this->parseName(),
            'arguments' => $this->parseArguments(),
            'loc' => $this->loc($start)
        ]);
    }

    // Implements the parsing rules in the Types section.

    /**
     * Handles the Type: TypeName, ListType, and NonNullType parsing rules.
     *
     * @return ListTypeNode|NameNode|NonNullTypeNode
     * @throws SyntaxError
     */
    function parseTypeReference()
    {
        $start = $this->lexer->token;

        if ($this->skip(Token::BRACKET_L)) {
            $type = $this->parseTypeReference();
            $this->expect(Token::BRACKET_R);
            $type = new ListTypeNode([
                'type' => $type,
                'loc' => $this->loc($start)
            ]);
        } else {
            $type = $this->parseNamedType();
        }
        if ($this->skip(Token::BANG)) {
            return new NonNullTypeNode([
                'type' => $type,
                'loc' => $this->loc($start)
            ]);

        }
        return $type;
    }

    function parseNamedType()
    {
        $start = $this->lexer->token;

        return new NamedTypeNode([
            'name' => $this->parseName(),
            'loc' => $this->loc($start)
        ]);
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
     * @return TypeSystemDefinitionNode
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
     * @return SchemaDefinitionNode
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

        return new SchemaDefinitionNode([
            'directives' => $directives,
            'operationTypes' => $operationTypes,
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @return OperationTypeDefinitionNode
     */
    function parseOperationTypeDefinition()
    {
        $start = $this->lexer->token;
        $operation = $this->parseOperationType();
        $this->expect(Token::COLON);
        $type = $this->parseNamedType();

        return new OperationTypeDefinitionNode([
            'operation' => $operation,
            'type' => $type,
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @return ScalarTypeDefinitionNode
     * @throws SyntaxError
     */
    function parseScalarTypeDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('scalar');
        $name = $this->parseName();
        $directives = $this->parseDirectives();

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new ScalarTypeDefinitionNode([
            'name' => $name,
            'directives' => $directives,
            'loc' => $this->loc($start),
            'description' => $description
        ]);
    }

    /**
     * @return ObjectTypeDefinitionNode
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

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new ObjectTypeDefinitionNode([
            'name' => $name,
            'interfaces' => $interfaces,
            'directives' => $directives,
            'fields' => $fields,
            'loc' => $this->loc($start),
            'description' => $description
        ]);
    }

    /**
     * @return NamedTypeNode[]
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
     * @return FieldDefinitionNode
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

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new FieldDefinitionNode([
            'name' => $name,
            'arguments' => $args,
            'type' => $type,
            'directives' => $directives,
            'loc' => $this->loc($start),
            'description' => $description
        ]);
    }

    /**
     * @return InputValueDefinitionNode[]|NodeList
     */
    function parseArgumentDefs()
    {
        if (!$this->peek(Token::PAREN_L)) {
            return [];
        }
        return $this->many(Token::PAREN_L, [$this, 'parseInputValueDef'], Token::PAREN_R);
    }

    /**
     * @return InputValueDefinitionNode
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
        $description = $this->getDescriptionFromAdjacentCommentTokens($start);
        return new InputValueDefinitionNode([
            'name' => $name,
            'type' => $type,
            'defaultValue' => $defaultValue,
            'directives' => $directives,
            'loc' => $this->loc($start),
            'description' => $description
        ]);
    }

    /**
     * @return InterfaceTypeDefinitionNode
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

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new InterfaceTypeDefinitionNode([
            'name' => $name,
            'directives' => $directives,
            'fields' => $fields,
            'loc' => $this->loc($start),
            'description' => $description
        ]);
    }

    /**
     * @return UnionTypeDefinitionNode
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

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new UnionTypeDefinitionNode([
            'name' => $name,
            'directives' => $directives,
            'types' => $types,
            'loc' => $this->loc($start),
            'description' => $description
        ]);
    }

    /**
     * UnionMembers :
     *   - `|`? NamedType
     *   - UnionMembers | NamedType
     *
     * @return NamedTypeNode[]
     */
    function parseUnionMembers()
    {
        // Optional leading pipe
        $this->skip(Token::PIPE);
        $members = [];

        do {
            $members[] = $this->parseNamedType();
        } while ($this->skip(Token::PIPE));
        return $members;
    }

    /**
     * @return EnumTypeDefinitionNode
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

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new EnumTypeDefinitionNode([
            'name' => $name,
            'directives' => $directives,
            'values' => $values,
            'loc' => $this->loc($start),
            'description' => $description
        ]);
    }

    /**
     * @return EnumValueDefinitionNode
     */
    function parseEnumValueDefinition()
    {
        $start = $this->lexer->token;
        $name = $this->parseName();
        $directives = $this->parseDirectives();

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new EnumValueDefinitionNode([
            'name' => $name,
            'directives' => $directives,
            'loc' => $this->loc($start),
            'description' => $description
        ]);
    }

    /**
     * @return InputObjectTypeDefinitionNode
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

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new InputObjectTypeDefinitionNode([
            'name' => $name,
            'directives' => $directives,
            'fields' => $fields,
            'loc' => $this->loc($start),
            'description' => $description
        ]);
    }

    /**
     * @return TypeExtensionDefinitionNode
     * @throws SyntaxError
     */
    function parseTypeExtensionDefinition()
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $definition = $this->parseObjectTypeDefinition();

        return new TypeExtensionDefinitionNode([
            'definition' => $definition,
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * DirectiveDefinition :
     *   - directive @ Name ArgumentsDefinition? on DirectiveLocations
     *
     * @return DirectiveDefinitionNode
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

        return new DirectiveDefinitionNode([
            'name' => $name,
            'arguments' => $args,
            'locations' => $locations,
            'loc' => $this->loc($start)
        ]);
    }

    /**
     * @return NameNode[]
     */
    function parseDirectiveLocations()
    {
        // Optional leading pipe
        $this->skip(Token::PIPE);
        $locations = [];
        do {
            $locations[] = $this->parseName();
        } while ($this->skip(Token::PIPE));
        return $locations;
    }

    /**
     * @param Token $nameToken
     * @return null|string
     */
    private function getDescriptionFromAdjacentCommentTokens(Token $nameToken)
    {
        $description = null;

        $currentToken = $nameToken;
        $previousToken = $currentToken->prev;

        while ($previousToken->kind == Token::COMMENT
            && ($previousToken->line + 1) == $currentToken->line
        ) {
            $description = $previousToken->value . $description;

            // walk the tokens backwards until no longer adjacent comments
            $currentToken = $previousToken;
            $previousToken = $currentToken->prev;
        }

        return $description;
    }
}
