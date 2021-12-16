<?php

namespace GraphQL\Tests\Validator\CustomRuleTest;

use GraphQL\Validator\Rules\ExecutableDefinitions;

class CustomExecutableDefinitions extends ExecutableDefinitions
{
    public static function nonExecutableDefinitionMessage(string $defName): string
    {
        return "Custom message including {$defName}";
    }
}
