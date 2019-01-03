<?php

namespace Proto\Pack\Lab;

new MakeTypes(__DIR__);

class MakeTypes
{
    private static $HTLimited = [
        0 => [0, null],

        1 => [2, 1, 'C'],
        2 => [2, 1, 'c'],
        3 => [2, 2, 'S'],
        4 => [2, 2, 's'],
        5 => [2, 4, 'L'],
        6 => [2, 4, 'l'],
        7 => [2, 8, 'q'],

        8 => [3, 1],
        9 => [3, 2],

        10 => [4, 1],
        11 => [4, 2],
    ];

    private static $HT = [
        0 => [0, null],

        1 => [1, true],
        2 => [1, false],

        3 => [2, 1, 'C'],
        4 => [2, 1, 'c'],
        5 => [2, 2, 'S'],
        6 => [2, 2, 's'],
        7 => [2, 4, 'L'],
        8 => [2, 4, 'l'],
        9 => [2, 8, 'q'],
        10 => [2, 4, 'f'],
        11 => [2, 8, 'd'],

        12 => [3, 1],
        13 => [3, 2],
        14 => [3, 4],
        15 => [3, 8],

        16 => [4, 1],
        17 => [4, 2],
        18 => [4, 4],
        19 => [4, 8]
    ];

    private static $DT = [
        0 => [0, null],

        1 => [1, true],
        2 => [1, false],

        3 => [2, 1, 'C'],
        4 => [2, 1, 'c'],
        5 => [2, 2, 'S'],
        6 => [2, 2, 's'],
        7 => [2, 4, 'L'],
        8 => [2, 4, 'l'],
        9 => [2, 8, 'q'],
        10 => [2, 4, 'f'],
        11 => [2, 8, 'd'],

        12 => [3, 1],
        13 => [3, 2],
        14 => [3, 4],
        15 => [3, 8],

        16 => [4, 1],
        17 => [4, 2],
        18 => [4, 4],
        19 => [4, 8]
    ];

    public function __construct($path)
    {
        // Header limited
        $ID2T = [];
        $i = 0;
        for ($h = 0; $h <= 11; $h++) {
            for ($d = 0; $d <= 19; $d++) {
                $ID2T[pack('C', $i)] = [self::$HTLimited[$h], self::$DT[$d]];
                $i++;
            }
        }
        file_put_contents($path . '/ID2T-Limited', bin2hex(msgpack_pack($ID2T)));

        // Header Full
        $ID2T = [];
        $i = 0;
        for ($h = 0; $h <= 19; $h++) {
            for ($d = 0; $d <= 19; $d++) {
                $ID2T[pack('S', $i)] = [self::$HT[$h], self::$DT[$d]];
                $i++;
            }
        }
        file_put_contents($path . '/ID2T', bin2hex(msgpack_pack($ID2T)));
    }
}