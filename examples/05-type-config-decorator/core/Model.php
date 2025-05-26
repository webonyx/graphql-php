<?php declare(strict_types=1);

namespace Core;

abstract class Model
{
    /**
     * @throws \RuntimeException
     *
     * @return mixed
     */
    protected static function get(string $endpoint)
    {
        $url = "https://odyssey-lift-off-rest-api.herokuapp.com/{$endpoint}";

        $contents = file_get_contents($url);
        if ($contents === false) {
            throw new \RuntimeException("Failed to fetch data from URL: {$url}.");
        }

        return json_decode($contents, true);
    }
}
