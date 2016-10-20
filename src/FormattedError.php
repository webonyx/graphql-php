<?php
namespace GraphQL;

use GraphQL\Language\SourceLocation;

class FormattedError
{
    /**
     * @deprecated since 2016-10-21
     * @param $error
     * @param SourceLocation[] $locations
     * @return array
     */
    public static function create($error, array $locations = [])
    {
        $formatted = [
            'message' => $error
        ];

        if (!empty($locations)) {
            $formatted['locations'] = array_map(function($loc) { return $loc->toArray();}, $locations);
        }

        return $formatted;
    }
}
