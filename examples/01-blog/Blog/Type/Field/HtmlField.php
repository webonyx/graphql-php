<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type\Field;

use GraphQL\Examples\Blog\Type\Enum\ContentFormatType;
use GraphQL\Examples\Blog\Types;

use function mb_substr;
use function nl2br;
use function strip_tags;

class HtmlField
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $objectKey): array
    {
        // Demonstrates how to organize re-usable fields
        // Usual example: when the same field with same args shows up in different types
        // (for example when it is a part of some interface)
        return [
            'type' => Types::string(),
            'args' => [
                'format' => [
                    'type' => Types::contentFormat(),
                    'defaultValue' => ContentFormatType::FORMAT_HTML,
                ],
                'maxLength' => Types::int(),
            ],
            'resolve' => static function ($object, $args) use ($objectKey) {
                $html = $object->{$objectKey};
                $text = strip_tags($html);

                if (isset($args['maxLength'])) {
                    $safeText = mb_substr($text, 0, $args['maxLength']);
                } else {
                    $safeText = $text;
                }

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
