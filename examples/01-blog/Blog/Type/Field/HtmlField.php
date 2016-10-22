<?php
namespace GraphQL\Examples\Blog\Type\Field;

use GraphQL\Examples\Blog\Type\Enum\ContentFormatEnum;
use GraphQL\Examples\Blog\TypeSystem;

class HtmlField
{
    public static function build(TypeSystem $types, $name, $objectKey = null)
    {
        $objectKey = $objectKey ?: $name;

        return [
            'name' => $name,
            'type' => $types->string(),
            'args' => [
                'format' => [
                    'type' => $types->contentFormatEnum(),
                    'defaultValue' => ContentFormatEnum::FORMAT_HTML
                ],
                'maxLength' => $types->int()
            ],
            'resolve' => function($object, $args) use ($objectKey) {
                $html = $object->{$objectKey};
                $text = strip_tags($html);

                if (!empty($args['maxLength'])) {
                    $safeText = mb_substr($text, 0, $args['maxLength']);
                } else {
                    $safeText = $text;
                }

                switch ($args['format']) {
                    case ContentFormatEnum::FORMAT_HTML:
                        if ($safeText !== $text) {
                            // Text was truncated, so just show what's safe:
                            return nl2br($safeText);
                        } else {
                            return $html;
                        }

                    case ContentFormatEnum::FORMAT_TEXT:
                    default:
                        return $safeText;
                }
            }
        ];
    }
}
