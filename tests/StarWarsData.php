<?php declare(strict_types=1);

namespace GraphQL\Tests;

final class StarWarsData
{
    /** @return array<string, mixed> */
    public static function character(string $id): ?array
    {
        $key = (int) $id;

        return self::humans()[$key]
            ?? self::droids()[$key]
            ?? null;
    }

    /** @return array<int, mixed> */
    public static function humans(): array
    {
        return [
            1000 => self::luke(),
            1001 => self::vader(),
            1002 => self::han(),
            1003 => self::leia(),
            1004 => self::tarkin(),
        ];
    }

    /** @return array<string, mixed> */
    private static function luke(): array
    {
        return [
            'id' => '1000',
            'name' => 'Luke Skywalker',
            'friends' => ['1002', '1003', '2000', '2001'],
            'appearsIn' => [4, 5, 6],
            'homePlanet' => 'Tatooine',
        ];
    }

    /** @return array<string, mixed> */
    private static function vader(): array
    {
        return [
            'id' => '1001',
            'name' => 'Darth Vader',
            'friends' => ['1004'],
            'appearsIn' => [4, 5, 6],
            'homePlanet' => 'Tatooine',
        ];
    }

    /** @return array<string, mixed> */
    private static function han(): array
    {
        return [
            'id' => '1002',
            'name' => 'Han Solo',
            'friends' => ['1000', '1003', '2001'],
            'appearsIn' => [4, 5, 6],
        ];
    }

    /** @return array<string, mixed> */
    private static function leia(): array
    {
        return [
            'id' => '1003',
            'name' => 'Leia Organa',
            'friends' => ['1000', '1002', '2000', '2001'],
            'appearsIn' => [4, 5, 6],
            'homePlanet' => 'Alderaan',
        ];
    }

    /** @return array<string, mixed> */
    private static function tarkin(): array
    {
        return [
            'id' => '1004',
            'name' => 'Wilhuff Tarkin',
            'friends' => ['1001'],
            'appearsIn' => [4],
        ];
    }

    /** @return array<int, mixed> */
    public static function droids(): array
    {
        return [
            2000 => self::threepio(),
            2001 => self::artoo(),
        ];
    }

    /** @return array<string, mixed> */
    private static function threepio(): array
    {
        return [
            'id' => '2000',
            'name' => 'C-3PO',
            'friends' => ['1000', '1002', '1003', '2001'],
            'appearsIn' => [4, 5, 6],
            'primaryFunction' => 'Protocol',
        ];
    }

    /** @return array<string, mixed> */
    private static function artoo(): array
    {
        return [
            'id' => '2001',
            'name' => 'R2-D2',
            'friends' => ['1000', '1002', '1003'],
            'appearsIn' => [4, 5, 6],
            'primaryFunction' => 'Astromech',
        ];
    }

    /**
     * @param array<string, mixed> $character
     *
     * @return array<int, mixed>
     */
    public static function friends(array $character): array
    {
        return array_map([self::class, 'character'], $character['friends']);
    }

    /** @return array<string, mixed> */
    public static function hero(?int $episode): array
    {
        if ($episode === 5) {
            // Luke is the hero of Episode V.
            return self::luke();
        }

        // Artoo is the hero otherwise.
        return self::artoo();
    }

    /** @return array<string, mixed>|null */
    public static function human(string $id): ?array
    {
        return self::humans()[(int) $id] ?? null;
    }

    /** @return array<string, mixed>|null */
    public static function droid(string $id): ?array
    {
        return self::droids()[(int) $id] ?? null;
    }
}
