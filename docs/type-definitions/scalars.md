# Scalar Type Definition

Scalar types represent primitive leaf values in a GraphQL type system.
When object fields have to resolve to some concrete data, that's where the scalar types come in.

## Built-in Scalar Types

The GraphQL specification describes several built-in scalar types. In **graphql-php** they are
exposed as static methods of the class [`GraphQL\Type\Definition\Type`](../class-reference.md#graphqltypedefinitiontype):

```php
use GraphQL\Type\Definition\Type;

// Built-in Scalar types:
Type::string();  // String type
Type::int();     // Int type
Type::float();   // Float type
Type::boolean(); // Boolean type
Type::id();      // ID type
```

Those methods return instances of a subclass of `GraphQL\Type\Definition\ScalarType`.
Use them directly in type definitions or wrapped in a type registry (see [lazy loading of types](../schema-definition.md#lazy-loading-of-types)).

## Writing Custom Scalar Types

In addition to built-in scalars, you can define your own scalar types with additional validation.
Typical examples of such types are **Email**, **Date**, **Url**, etc.

In order to implement your own type, you must understand how scalars are handled in GraphQL.
GraphQL deals with scalars in the following cases:

1. Convert the **internal representation** of a value, returned by your app (e.g. stored in a database
   or hardcoded in the source code), to a **serialized representation** included in the response.

2. Convert an **input value**, passed by a client in variables along with a GraphQL query, to
   its **internal representation** used in your application.

3. Convert an **input literal value**, hardcoded in a GraphQL query (e.g. field argument value), to
   its **internal representation** used in your application.

Those cases are covered by the methods `serialize`, `parseValue` and `parseLiteral` of the
abstract class `ScalarType` respectively.

Here is an example of a simple **Email** type:

```php
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class EmailType extends ScalarType
{
    // Note: name can be omitted. In this case it will be inferred from class name
    // (suffix "Type" will be dropped)
    public string $name = 'Email';

    public function serialize($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvariantViolation("Could not serialize following value as email: " . Utils::printSafe($value));
        }

        return $this->parseValue($value);
    }

    public function parseValue($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Error("Cannot represent following value as email: " . Utils::printSafeJson($value));
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        // Throw GraphQL\Error\Error vs \UnexpectedValueException to locate the error in the query
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, [$valueNode]);
        }

        if (!filter_var($valueNode->value, FILTER_VALIDATE_EMAIL)) {
            throw new Error("Not a valid email", [$valueNode]);
        }

        return $valueNode->value;
    }
}
```

Or with inline style:

```php
use GraphQL\Type\Definition\CustomScalarType;

$emailType = new CustomScalarType([
    'name' => 'Email',
    'serialize' => static function ($value) {/* See function body above */},
    'parseValue' => static function ($value) {/* See function body above */},
    'parseLiteral' => static function (Node $valueNode, ?array $variables = null) {/* See function body above */},
]);
```

Keep in mind the passed functions will be called statically, so a passed in `callable`
such as `[Foo::class, 'bar']` should only reference static class methods.
