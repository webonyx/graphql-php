<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog;

use GraphQL\Error\InvariantViolation;
use GraphQL\Examples\Blog\Type\CommentType;
use GraphQL\Examples\Blog\Type\Enum\ContentFormatType;
use GraphQL\Examples\Blog\Type\Enum\ImageSizeType;
use GraphQL\Examples\Blog\Type\Enum\StoryAffordancesType;
use GraphQL\Examples\Blog\Type\ImageType;
use GraphQL\Examples\Blog\Type\NodeType;
use GraphQL\Examples\Blog\Type\Scalar\EmailType;
use GraphQL\Examples\Blog\Type\Scalar\UrlType;
use GraphQL\Examples\Blog\Type\SearchResultType;
use GraphQL\Examples\Blog\Type\StoryType;
use GraphQL\Examples\Blog\Type\UserType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

/**
 * Acts as a registry and factory for your types.
 *
 * As simplistic as possible for the sake of clarity of this example.
 * Your own may be more dynamic (or even code-generated).
 */
final class Types
{
    /** @var array<string, Type&NamedType> */
    private static array $types = [];

    /**
     * @throws \Exception
     *
     * @return Type&NamedType
     */
    public static function load(string $typeName): Type
    {
        if (isset(self::$types[$typeName])) {
            return self::$types[$typeName];
        }

        // For every type, this class must define a method with the same name
        // but the first letter is in lower case.
        switch ($typeName) {
            case 'ID':
                $methodName = 'id';
                break;
            default:
                $methodName = \lcfirst($typeName);
        }
        if (! method_exists(self::class, $methodName)) {
            throw new \Exception("Unknown GraphQL type: {$typeName}.");
        }

        $type = self::{$methodName}(); // @phpstan-ignore-line variable static method call
        if (is_callable($type)) {
            $type = $type();
        }

        return self::$types[$typeName] = $type;
    }

    /**
     * @param class-string<Type&NamedType> $className
     *
     * @return Type&NamedType
     */
    private static function byClassName(string $className): Type
    {
        $classNameParts = \explode('\\', $className);
        $baseClassName = end($classNameParts);
        // All type classes must use the suffix Type.
        // This prevents name collisions between types and PHP keywords.
        $typeName = \preg_replace('~Type$~', '', $baseClassName);
        assert(is_string($typeName), 'regex is statically known to be correct');

        // Type loading is very similar to PHP class loading, but keep in mind
        // that the **typeLoader** must always return the same instance of a type.
        // We can enforce that in our type registry by caching known types.
        return self::$types[$typeName] ??= new $className();
    }

    /**
     * @param class-string<Type&NamedType> $classname
     *
     * @return \Closure(): (Type&NamedType)
     */
    private static function lazyByClassName(string $classname): \Closure
    {
        return static fn () => self::byClassName($classname);
    }

    /** @throws InvariantViolation */
    public static function boolean(): ScalarType
    {
        return Type::boolean();
    }

    /** @throws InvariantViolation */
    public static function float(): ScalarType
    {
        return Type::float();
    }

    /** @throws InvariantViolation */
    public static function id(): ScalarType
    {
        return Type::id();
    }

    /** @throws InvariantViolation */
    public static function int(): ScalarType
    {
        return Type::int();
    }

    /** @throws InvariantViolation */
    public static function string(): ScalarType
    {
        return Type::string();
    }

    public static function user(): callable
    {
        return self::lazyByClassName(UserType::class);
    }

    public static function story(): callable
    {
        return self::lazyByClassName(StoryType::class);
    }

    public static function comment(): callable
    {
        return self::lazyByClassName(CommentType::class);
    }

    public static function image(): callable
    {
        return self::lazyByClassName(ImageType::class);
    }

    public static function node(): callable
    {
        return self::lazyByClassName(NodeType::class);
    }

    public static function mention(): callable
    {
        return self::lazyByClassName(SearchResultType::class);
    }

    public static function imageSize(): callable
    {
        return self::lazyByClassName(ImageSizeType::class);
    }

    public static function contentFormat(): callable
    {
        return self::lazyByClassName(ContentFormatType::class);
    }

    public static function storyAffordances(): callable
    {
        return self::lazyByClassName(StoryAffordancesType::class);
    }

    public static function email(): callable
    {
        return self::lazyByClassName(EmailType::class);
    }

    public static function url(): callable
    {
        return self::lazyByClassName(UrlType::class);
    }
}
