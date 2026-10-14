<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type\Field;

use GraphQL\Error\InvariantViolation;
use GraphQL\Examples\Blog\Type\Enum\ContentFormatType;
use GraphQL\Examples\Blog\TypeRegistry;
use GraphQL\Type\Definition\Type;

class HtmlField
{
    /**
     * @param array{
     * 	resolve: callable
     * } $config
     *
     * @throws InvariantViolation
     *
     * @return array<mixed>
     */
    public static function build(array $config): array
    {
        $resolver = $config['resolve'];

        // Demonstrates how to organize re-usable fields
        // Usual example: when the same field with same args shows up in different types
        // (for example when it is a part of some interface)
        return [
            'type' => Type::string(),
            'args' => [
                'format' => [
                    'type' => TypeRegistry::type(ContentFormatType::class),
                    'defaultValue' => ContentFormatType::FORMAT_HTML,
                ],
                'maxLength' => Type::int(),
            ],
            'resolve' => static function ($rootValue, array $args) use ($resolver): ?string {
                $html = $resolver($rootValue, $args);
                $text = strip_tags($html);

                $safeText = isset($args['maxLength'])
                    ? mb_substr($text, 0, $args['maxLength'])
                    : $text;

                switch ($args['format']) {
                    case ContentFormatType::FORMAT_HTML:
                        if ($safeText !== $text) {
                            // Text was truncated, so just show what's safe:
                            return nl2br($safeText);
                        }

                        return $html;

                    case ContentFormatType::FORMAT_TEXT:
                    default:
                        return $safeText;
                }
            },
        ];
    }
}
