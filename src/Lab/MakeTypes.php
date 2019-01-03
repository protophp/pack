<?php

namespace Proto\Pack\Lab;

new MakeTypes(__DIR__);

class MakeTypes
{
    private static $HTLimited = [
        0 => [0, null],

        1 => [1, 1, 'C'],
        2 => [1, 1, 'c'],
        3 => [1, 2, 'S'],
        4 => [1, 2, 's'],
        5 => [1, 4, 'L'],
        6 => [1, 4, 'l'],
        7 => [1, 8, 'q'],

        8 => [2, 1],
        9 => [2, 2],

        10 => [3, 1],
        11 => [3, 2],
    ];

    private static $HT = [
        0 => [0, null],
        1 => [0, true],
        2 => [0, false],

        3 => [1, 1, 'C'],
        4 => [1, 1, 'c'],
        5 => [1, 2, 'S'],
        6 => [1, 2, 's'],
        7 => [1, 4, 'L'],
        8 => [1, 4, 'l'],
        9 => [1, 8, 'q'],
        10 => [1, 4, 'f'],
        11 => [1, 8, 'd'],

        12 => [2, 1],
        13 => [2, 2],
        14 => [2, 4],
        15 => [2, 8],

        16 => [3, 1],
        17 => [3, 2],
        18 => [3, 4],
        19 => [3, 8]
    ];

    private static $DT = [
        0 => [0, null],
        1 => [0, true],
        2 => [0, false],

        3 => [1, 1, 'C'],
        4 => [1, 1, 'c'],
        5 => [1, 2, 'S'],
        6 => [1, 2, 's'],
        7 => [1, 4, 'L'],
        8 => [1, 4, 'l'],
        9 => [1, 8, 'q'],
        10 => [1, 4, 'f'],
        11 => [1, 8, 'd'],

        12 => [2, 1],
        13 => [2, 2],
        14 => [2, 4],
        15 => [2, 8],

        16 => [3, 1],
        17 => [3, 2],
        18 => [3, 4],
        19 => [3, 8]
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