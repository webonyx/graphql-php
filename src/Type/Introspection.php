<?php declare(strict_types=1);

namespace GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Utils\Utils;

/**
 * @phpstan-type IntrospectionOptions array{
 *     descriptions?: bool,
 *     directiveIsRepeatable?: bool,
 *     schemaDescription?: bool,
 *     typeIsOneOf?: bool,
 * }
 *
 * Available options:
 * - descriptions
 *   Include descriptions in the introspection result?
 *   Default: true
 * - directiveIsRepeatable
 *   Include field `isRepeatable` for directives?
 *   Default: false
 * - typeIsOneOf
 *   Include field `isOneOf` for types?
 *   Default: false
 *
 * @see \GraphQL\Tests\Type\IntrospectionTest
 */
class Introspection
{
    public const SCHEMA_FIELD_NAME = '__schema';
    public const TYPE_FIELD_NAME = '__type';
    public const TYPE_NAME_FIELD_NAME = '__typename';

    public const SCHEMA_OBJECT_NAME = '__Schema';
    public const TYPE_OBJECT_NAME = '__Type';
    public const DIRECTIVE_OBJECT_NAME = '__Directive';
    public const FIELD_OBJECT_NAME = '__Field';
    public const INPUT_VALUE_OBJECT_NAME = '__InputValue';
    public const ENUM_VALUE_OBJECT_NAME = '__EnumValue';
    public const TYPE_KIND_ENUM_NAME = '__TypeKind';
    public const DIRECTIVE_LOCATION_ENUM_NAME = '__DirectiveLocation';

    /**
     * @param IntrospectionOptions $options
     *
     * @api
     */
    public static function getIntrospectionQuery(array $options = []): string
    {
        $optionsWithDefaults = array_merge([
            'descriptions' => true,
            'directiveIsRepeatable' => false,
            'schemaDescription' => false,
            'typeIsOneOf' => false,
        ], $options);

        $descriptions = $optionsWithDefaults['descriptions']
            ? 'description'
            : '';
        $directiveIsRepeatable = $optionsWithDefaults['directiveIsRepeatable']
            ? 'isRepeatable'
            : '';
        $schemaDescription = $optionsWithDefaults['schemaDescription']
            ? $descriptions
            : '';
        $typeIsOneOf = $optionsWithDefaults['typeIsOneOf']
            ? 'isOneOf'
            : '';

        return <<<GRAPHQL
  query IntrospectionQuery {
    __schema {
      {$schemaDescription}
      queryType { name }
      mutationType { name }
      subscriptionType { name }
      types {
        ...FullType
      }
      directives {
        name
        {$descriptions}
        args(includeDeprecated: true) {
          ...InputValue
        }
        {$directiveIsRepeatable}
        locations
      }
    }
  }

  fragment FullType on __Type {
    kind
    name
    {$descriptions}
    {$typeIsOneOf}
    fields(includeDeprecated: true) {
      name
      {$descriptions}
      args(includeDeprecated: true) {
        ...InputValue
      }
      type {
        ...TypeRef
      }
      isDeprecated
      deprecationReason
    }
    inputFields(includeDeprecated: true) {
      ...InputValue
    }
    interfaces {
      ...TypeRef
    }
    enumValues(includeDeprecated: true) {
      name
      {$descriptions}
      isDeprecated
      deprecationReason
    }
    possibleTypes {
      ...TypeRef
    }
  }

  fragment InputValue on __InputValue {
    name
    {$descriptions}
    type { ...TypeRef }
    defaultValue
    isDeprecated
    deprecationReason
  }

  fragment TypeRef on __Type {
    kind
    name
    ofType {
      kind
      name
      ofType {
        kind
        name
        ofType {
          kind
          name
          ofType {
            kind
            name
            ofType {
              kind
              name
              ofType {
                kind
                name
                ofType {
                  kind
                  name
                }
              }
            }
          }
        }
      }
    }
  }
GRAPHQL;
    }

    /**
     * Build an introspection query from a Schema.
     *
     * Introspection is useful for utilities that care about type and field
     * relationships, but do not need to traverse through those relationships.
     *
     * This is the inverse of BuildClientSchema::build(). The primary use case is
     * outside the server context, for instance when doing schema comparisons.
     *
     * @param IntrospectionOptions $options
     *
     * @throws \Exception
     * @throws \JsonException
     * @throws InvariantViolation
     *
     * @return array<string, array<mixed>>
     *
     * @api
     */
    public static function fromSchema(Schema $schema, array $options = []): array
    {
        $optionsWithDefaults = array_merge([
            'directiveIsRepeatable' => true,
            'schemaDescription' => true,
            'typeIsOneOf' => true,
        ], $options);

        $result = GraphQL::executeQuery(
            $schema,
            self::getIntrospectionQuery($optionsWithDefaults)
        );

        $data = $result->data;
        if ($data === null) {
            $noDataResult = Utils::printSafeJson($result);
            throw new InvariantViolation("Introspection query returned no data: {$noDataResult}.");
        }

        return $data;
    }
}
