<?php

namespace GraphQL\Type\Builder;

class FieldsConfig extends Config
{
    use FieldsConfigTrait;

    public function build()
    {
        $fields = parent::build();

        return isset($fields['fields']) ? $fields['fields'] : [];
    }
}
