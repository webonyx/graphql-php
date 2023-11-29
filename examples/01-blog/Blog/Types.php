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

    public static function user(): callable
    {
        return self::get(UserType::class);
    }

    public static function story(): callable
    {
        return self::get(StoryType::class);
    }

    public static function comment(): callable
    {
        return self::get(CommentType::class);
    }

    public static function image(): callable
    {
        return self::get(ImageType::class);
    }

    public static function node(): callable
    {
        return self::get(NodeType::class);
    }

    public static function mention(): callable
    {
        return self::get(SearchResultType::class);
    }

    public static function imageSize(): callable
    {
        return self::get(ImageSizeType::class);
    }

    public static function contentFormat(): callable
    {
        return self::get(ContentFormatType::class);
    }

    public static function storyAffordances(): callable
    {
        return self::get(StoryAffordancesType::class);
    }

    public static function email(): callable
    {
        return self::get(EmailType::class);
    }

    public static function url(): callable
    {
        return self::get(UrlType::class);
    }

    /**
     * @param class-string<Type&NamedType> $classname
     *
     * @return \Closure(): Type
     */
    private static function get(string $classname): \Closure
    {
        return static fn () => self::byClassName($classname);
    }

    /** @param class-string<Type&NamedType> $classname */
    private static function byClassName(string $classname): Type
    {
        $parts = \explode('\\', $classname);

        $withoutTypePrefix = \preg_replace('~Type$~', '', $parts[\count($parts) - 1]);
        assert(is_string($withoutTypePrefix), 'regex is statically known to be correct');

        $cacheName = \strtolower($withoutTypePrefix);

        if (! isset(self::$types[$cacheName])) {
            return self::$types[$cacheName] = new $classname();
        }

        return self::$types[$cacheName];
    }

    /**
     * @throws \Exception
     *
     * @return Type&NamedType
     */
    public static function byTypeName(string $shortName): Type
    {
        $method = lcfirst($shortName);
        if(method_exists(__CLASS__, $method))
            return self::{$method}()();

        throw new \Exception("Unknown graphql type: {$shortName}");
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
}
