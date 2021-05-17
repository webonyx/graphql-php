<?php

declare(strict_types=1);

interface Resolver
{
    public function __invoke($rootValue, array $args, $context);
}

class SumResolver implements Resolver
{
    /**
     * @param array{x: int, y: int} $args
     */
    public function __invoke($rootValue, array $args, $context): int
    {
        return $args['x'] + $args['y'];
    }
}

class EchoResolver implements Resolver
{
    /**
     * @param array{message: string} $args
     */
    public function __invoke($rootValue, array $args, $context): string
    {
        return $rootValue['prefix'] . $args['message'];
    }
}

return [
    'sum' => new SumResolver(),
    'echo' => new EchoResolver(),
    'prefix' => 'You said: ',
];
