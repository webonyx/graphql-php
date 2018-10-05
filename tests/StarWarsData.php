<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use function array_map;

class StarWarsData
{
    /**
     * Helper function to get a character by ID.
     */
    public static function getCharacter($id)
    {
        $humans = self::humans();
        $droids = self::droids();
        if (isset($humans[$id])) {
            return $humans[$id];
        }
        if (isset($droids[$id])) {
            return $droids[$id];
        }

        return null;
    }

    public static function humans()
    {
        return [
            '1000' => self::luke(),
            '1001' => self::vader(),
            '1002' => self::han(),
            '1003' => self::leia(),
            '1004' => self::tarkin(),
        ];
    }

    private static function luke()
    {
        return [
            'id'         => '1000',
            'name'       => 'Luke Skywalker',
            'friends'    => ['1002', '1003', '2000', '2001'],
            'appearsIn'  => [4, 5, 6],
            'homePlanet' => 'Tatooine',
        ];
    }

    private static function vader()
    {
        return [
            'id'         => '1001',
            'name'       => 'Darth Vader',
            'friends'    => ['1004'],
            'appearsIn'  => [4, 5, 6],
            'homePlanet' => 'Tatooine',
        ];
    }

    private static function han()
    {
        return [
            'id'        => '1002',
            'name'      => 'Han Solo',
            'friends'   => ['1000', '1003', '2001'],
            'appearsIn' => [4, 5, 6],
        ];
    }

    private static function leia()
    {
        return [
            'id'         => '1003',
            'name'       => 'Leia Organa',
            'friends'    => ['1000', '1002', '2000', '2001'],
            'appearsIn'  => [4, 5, 6],
            'homePlanet' => 'Alderaan',
        ];
    }

    private static function tarkin()
    {
        return [
            'id'        => '1004',
            'name'      => 'Wilhuff Tarkin',
            'friends'   => ['1001'],
            'appearsIn' => [4],
        ];
    }

    public static function droids()
    {
        return [
            '2000' => self::threepio(),
            '2001' => self::artoo(),
        ];
    }

    private static function threepio()
    {
        return [
            'id'              => '2000',
            'name'            => 'C-3PO',
            'friends'         => ['1000', '1002', '1003', '2001'],
            'appearsIn'       => [4, 5, 6],
            'primaryFunction' => 'Protocol',
        ];
    }

    /**
     * We export artoo directly because the schema returns him
     * from a root field, and hence needs to reference him.
     */
    private static function artoo()
    {
        return [

            'id'              => '2001',
            'name'            => 'R2-D2',
            'friends'         => ['1000', '1002', '1003'],
            'appearsIn'       => [4, 5, 6],
            'primaryFunction' => 'Astromech',
        ];
    }

    /**
     * Allows us to query for a character's friends.
     */
    public static function getFriends($character)
    {
        return array_map([self::class, 'getCharacter'], $character['friends']);
    }

    /**
     * @param int $episode
     *
     * @return mixed[]
     */
    public static function getHero($episode)
    {
        if ($episode === 5) {
            // Luke is the hero of Episode V.
            return self::luke();
        }

        // Artoo is the hero otherwise.
        return self::artoo();
    }

    /**
     * @param string $id
     *
     * @return mixed|null
     */
    public static function getHuman($id)
    {
        $humans = self::humans();

        return $humans[$id] ?? null;
    }

    /**
     * @param string $id
     *
     * @return mixed|null
     */
    public static function getDroid($id)
    {
        $droids = self::droids();

        return $droids[$id] ?? null;
    }
}
