<?php

namespace Proto\Pack;

use Opt\OptTrait;

class Pack implements PackInterface
{
    use BufferTrait;
    use OptTrait;

    private $data = null;
    private $header = null;
    private $string;
    private $HL;
    private $DL;

    private $bT;
    private $bHL;
    private $bDL;

    private $completed = false;

    private static $ID2T;

    public function __construct()
    {
        // Option defaults
        $this->setOpt(self::OPT_HEADER_LIMITED, true);
    }

    public function setData($data): PackInterface
    {
        $this->data = $data;
        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function isData(): bool
    {
        return isset($this->data);
    }

    public function setHeader($header): PackInterface
    {
        $this->header = $header;
        return $this;
    }

    public function setArrayHeader($name, $value): PackInterface
    {
        $this->header[$name] = $value;
        return $this;
    }

    public function getHeader()
    {
        return $this->header;
    }

    public function getArrayHeader($name)
    {
        return isset($this->header[$name]) ? $this->header[$name] : null;
    }

    public function isHeader(): bool
    {
        return isset($this->header);
    }

    public function mergeFrom(string $chunk): bool
    {
        if ($this->completed) {
            // TODO: Log warning error
            return false;
        }

        $this->buffer .= $chunk;
        $return = false;

        /*
         *  Binary pack structure
         *  -------------------------------
         *  | T | HL | DL | HData | DData |
         *  -------------------------------
         */

        // Parse T
        $this->parseT($return);
        if ($return) return false;

        // Parse HL
        if (!isset($this->bHL)) {
            $this->parseL($this->bT[0], $this->bHL, $return);
            if ($return) return false;
        }

        // Parse DL
        if (!isset($this->bDL)) {
            $this->parseL($this->bT[1], $this->bDL, $return);
            if ($return) return false;
        }

        // Parse HData
        if (!isset($this->header)) {
            $this->parseData($this->bT[0], $this->bHL, $this->header, $return);
            if ($return) return false;
        }

        // Parse DData
        if (!isset($this->data)) {
            $this->parseData($this->bT[1], $this->bDL, $this->data, $return);
            if ($return) return false;
        }

        $this->completed();
        return true;
    }

    private function parseT(&$return)
    {
        if (!isset($this->bT)) {
            if ($this->bufferLen() < 1) {
                $return = true;
                return;
            }
            $this->bT = self::$ID2T[$this->bufferTrim(1)];

            // TODO: Validate T
            // TODO: Log critical error
            // TODO: Throw PackException
        }
    }

    private function parseL(&$XT, &$XL, &$return)
    {
        if ($XT[0] === 0 || $XT[0] === 1 || $XT[0] === 2) { // NULL & BOOL & INT & FLOAT & DOUBLE
            $XL = 0;

        } elseif ($XT[0] === 3 || $XT[0] === 4) {   // ARRAY & OBJECT & STRING
            if ($this->bufferLen() < $XT[1]) {
                $return = true;
                return;
            }

            $XL = unpack($this->intLength2unsignedPackCode($XT[1]), $this->bufferTrim($XT[1]))[1];
        }
    }

    private function parseData(&$XT, &$XL, &$data, &$return)
    {
        if ($XT[0] === 0) {         // NULL
            $data = null;

        } elseif ($XT[0] === 1) {      // BOOL
            $data = $XT[1];

        } elseif ($XT[0] === 2) {   // INT & FLOAT & DOUBLE
            if ($this->bufferLen() < $XT[1]) {
                $return = true;
                return;
            }

            $data = unpack($XT[2], $this->bufferTrim($XT[1]))[1];

        } elseif ($XT[0] === 3) {   // ARRAY & OBJECT
            if ($this->bufferLen() < $XL) {
                $return = true;
                return;
            }

            $data = msgpack_unpack($this->bufferTrim($XL));

        } elseif ($XT[0] === 4) {   // STRING
            if ($this->bufferLen() < $XL) {
                $return = true;
                return;
            }

            $data = $this->bufferTrim($XL);
        }
    }

    public function toString(): string
    {
        if (isset($this->string))
            return $this->string;

        // Header limited mode
        if ($this->getOpt(self::OPT_HEADER_LIMITED))
            if (is_bool($this->header) || is_float($this->header) || is_double($this->header))
                throw new PackException(null, PackException::ERR_HEADER_UNSUPPORTED_TYPES);

        $HT = null;
        $DT = null;
        $HL = null;
        $DL = null;
        $HData = null;
        $DData = null;

        /*
         *  Binary pack structure
         *  -------------------------------
         *  | T | HL | DL | HData | DData |
         *  -------------------------------
         */

        // Prepare T
        $this->ID2T();

        // Prepare Header
        $this->prepare($this->header, $HT, $HL, $HData);

        // Header limited mode
        if ($this->getOpt(self::OPT_HEADER_LIMITED))
            if (strlen($HData) > 0xFFFF)
                throw new PackException(null, PackException::ERR_HEADER_TOO_LARGE);

        // Prepare Data
        $this->prepare($this->data, $DT, $DL, $DData);
        $T = array_search([$HT, $DT], self::$ID2T);

        $this->string = $T . $HL . $DL . $HData . $DData;
        return $this->string;
    }

    private function prepare($data, &$T, &$L, &$string)
    {
        if (is_null($data)) {
            $T = [0, $data];

        } elseif (is_bool($data)) {
            $T = [1, $data];

        } elseif (is_string($data)) {
            $string = $data;
            $L = strlen($data);
            list($code, $l) = $this->int2pack($L);
            $T = [4, $l];
            $L = pack($code, $L);

        } elseif (is_array($data) || is_object($data)) {
            $string = msgpack_pack($data);
            $L = strlen($string);
            list($code, $l) = $this->int2pack($L);
            $T = [3, $l];
            $L = pack($code, $L);

        } elseif (is_int($data)) {
            list($code, $l) = $this->int2pack($data);
            $string = pack($code, $data);
            $T = [2, $l, $code];

        } elseif (is_float($data)) {
            if ($data > -3.4E+38 && $data < +3.4E+38) {     // float
                $string = pack('f', $data);
                $T = [2, 4, 'f'];
            } else {                                        // double
                $string = pack('d', $data);
                $T = [2, 8, 'd'];
            }

        }
    }

    private function intLength2unsignedPackCode(int $length): string
    {
        switch ($length) {
            case 1:
                return 'C';
            case 2:
                return 'S';
            case 4:
                return 'L';
            default:    // 8
                return 'Q';
        }
    }

    private function int2pack($int): array
    {
        if ($int >= 0) {

            if ($int <= 0xFF)
                return ['C', 1];

            if ($int <= 0xFFFF)
                return ['S', 2];

            if ($int <= 0xFFFFFFFF)
                return ['L', 4];

            return ['q', 8];

        } else {

            if ($int > -0x80)
                return ['c', 1];

            if ($int > -0x8000)
                return ['s', 2];

            if ($int > -0x80000000)
                return ['l', 4];

            return ['q', 8];
        }
    }

    private function ID2T()
    {
        if (isset(self::$ID2T))
            return;

        if ($this->getOpt(self::OPT_HEADER_LIMITED))
            self::$ID2T = msgpack_unpack(hex2bin('de00f0a100929200c09200c0a101929200c09201c3a102929200c09201c2a103929200c0930201a143a104929200c0930201a163a105929200c0930202a153a106929200c0930202a173a107929200c0930204a14ca108929200c0930204a16ca109929200c0930208a171a10a929200c0930204a166a10b929200c0930208a164a10c929200c0920301a10d929200c0920302a10e929200c0920304a10f929200c0920308a110929200c0920401a111929200c0920402a112929200c0920404a113929200c0920408a11492930201a1439200c0a11592930201a1439201c3a11692930201a1439201c2a11792930201a143930201a143a11892930201a143930201a163a11992930201a143930202a153a11a92930201a143930202a173a11b92930201a143930204a14ca11c92930201a143930204a16ca11d92930201a143930208a171a11e92930201a143930204a166a11f92930201a143930208a164a12092930201a143920301a12192930201a143920302a12292930201a143920304a12392930201a143920308a12492930201a143920401a12592930201a143920402a12692930201a143920404a12792930201a143920408a12892930201a1639200c0a12992930201a1639201c3a12a92930201a1639201c2a12b92930201a163930201a143a12c92930201a163930201a163a12d92930201a163930202a153a12e92930201a163930202a173a12f92930201a163930204a14c0092930201a163930204a16c0192930201a163930208a1710292930201a163930204a1660392930201a163930208a1640492930201a1639203010592930201a1639203020692930201a1639203040792930201a1639203080892930201a1639204010992930201a163920402a13a92930201a163920404a13b92930201a163920408a13c92930202a1539200c0a13d92930202a1539201c3a13e92930202a1539201c2a13f92930202a153930201a143a14092930202a153930201a163a14192930202a153930202a153a14292930202a153930202a173a14392930202a153930204a14ca14492930202a153930204a16ca14592930202a153930208a171a14692930202a153930204a166a14792930202a153930208a164a14892930202a153920301a14992930202a153920302a14a92930202a153920304a14b92930202a153920308a14c92930202a153920401a14d92930202a153920402a14e92930202a153920404a14f92930202a153920408a15092930202a1739200c0a15192930202a1739201c3a15292930202a1739201c2a15392930202a173930201a143a15492930202a173930201a163a15592930202a173930202a153a15692930202a173930202a173a15792930202a173930204a14ca15892930202a173930204a16ca15992930202a173930208a171a15a92930202a173930204a166a15b92930202a173930208a164a15c92930202a173920301a15d92930202a173920302a15e92930202a173920304a15f92930202a173920308a16092930202a173920401a16192930202a173920402a16292930202a173920404a16392930202a173920408a16492930204a14c9200c0a16592930204a14c9201c3a16692930204a14c9201c2a16792930204a14c930201a143a16892930204a14c930201a163a16992930204a14c930202a153a16a92930204a14c930202a173a16b92930204a14c930204a14ca16c92930204a14c930204a16ca16d92930204a14c930208a171a16e92930204a14c930204a166a16f92930204a14c930208a164a17092930204a14c920301a17192930204a14c920302a17292930204a14c920304a17392930204a14c920308a17492930204a14c920401a17592930204a14c920402a17692930204a14c920404a17792930204a14c920408a17892930204a16c9200c0a17992930204a16c9201c3a17a92930204a16c9201c2a17b92930204a16c930201a143a17c92930204a16c930201a163a17d92930204a16c930202a153a17e92930204a16c930202a173a17f92930204a16c930204a14ca18092930204a16c930204a16ca18192930204a16c930208a171a18292930204a16c930204a166a18392930204a16c930208a164a18492930204a16c920301a18592930204a16c920302a18692930204a16c920304a18792930204a16c920308a18892930204a16c920401a18992930204a16c920402a18a92930204a16c920404a18b92930204a16c920408a18c92930208a1719200c0a18d92930208a1719201c3a18e92930208a1719201c2a18f92930208a171930201a143a19092930208a171930201a163a19192930208a171930202a153a19292930208a171930202a173a19392930208a171930204a14ca19492930208a171930204a16ca19592930208a171930208a171a19692930208a171930204a166a19792930208a171930208a164a19892930208a171920301a19992930208a171920302a19a92930208a171920304a19b92930208a171920308a19c92930208a171920401a19d92930208a171920402a19e92930208a171920404a19f92930208a171920408a1a0929203019200c0a1a1929203019201c3a1a2929203019201c2a1a392920301930201a143a1a492920301930201a163a1a592920301930202a153a1a692920301930202a173a1a792920301930204a14ca1a892920301930204a16ca1a992920301930208a171a1aa92920301930204a166a1ab92920301930208a164a1ac92920301920301a1ad92920301920302a1ae92920301920304a1af92920301920308a1b092920301920401a1b192920301920402a1b292920301920404a1b392920301920408a1b4929203029200c0a1b5929203029201c3a1b6929203029201c2a1b792920302930201a143a1b892920302930201a163a1b992920302930202a153a1ba92920302930202a173a1bb92920302930204a14ca1bc92920302930204a16ca1bd92920302930208a171a1be92920302930204a166a1bf92920302930208a164a1c092920302920301a1c192920302920302a1c292920302920304a1c392920302920308a1c492920302920401a1c592920302920402a1c692920302920404a1c792920302920408a1c8929204019200c0a1c9929204019201c3a1ca929204019201c2a1cb92920401930201a143a1cc92920401930201a163a1cd92920401930202a153a1ce92920401930202a173a1cf92920401930204a14ca1d092920401930204a16ca1d192920401930208a171a1d292920401930204a166a1d392920401930208a164a1d492920401920301a1d592920401920302a1d692920401920304a1d792920401920308a1d892920401920401a1d992920401920402a1da92920401920404a1db92920401920408a1dc929204029200c0a1dd929204029201c3a1de929204029201c2a1df92920402930201a143a1e092920402930201a163a1e192920402930202a153a1e292920402930202a173a1e392920402930204a14ca1e492920402930204a16ca1e592920402930208a171a1e692920402930204a166a1e792920402930208a164a1e892920402920301a1e992920402920302a1ea92920402920304a1eb92920402920308a1ec92920402920401a1ed92920402920402a1ee92920402920404a1ef92920402920408'));
        else
            self::$ID2T = msgpack_unpack(hex2bin('de0190a20000929200c09200c0a20100929200c09201c3a20200929200c09201c2a20300929200c0930201a143a20400929200c0930201a163a20500929200c0930202a153a20600929200c0930202a173a20700929200c0930204a14ca20800929200c0930204a16ca20900929200c0930208a171a20a00929200c0930204a166a20b00929200c0930208a164a20c00929200c0920301a20d00929200c0920302a20e00929200c0920304a20f00929200c0920308a21000929200c0920401a21100929200c0920402a21200929200c0920404a21300929200c0920408a21400929201c39200c0a21500929201c39201c3a21600929201c39201c2a21700929201c3930201a143a21800929201c3930201a163a21900929201c3930202a153a21a00929201c3930202a173a21b00929201c3930204a14ca21c00929201c3930204a16ca21d00929201c3930208a171a21e00929201c3930204a166a21f00929201c3930208a164a22000929201c3920301a22100929201c3920302a22200929201c3920304a22300929201c3920308a22400929201c3920401a22500929201c3920402a22600929201c3920404a22700929201c3920408a22800929201c29200c0a22900929201c29201c3a22a00929201c29201c2a22b00929201c2930201a143a22c00929201c2930201a163a22d00929201c2930202a153a22e00929201c2930202a173a22f00929201c2930204a14ca23000929201c2930204a16ca23100929201c2930208a171a23200929201c2930204a166a23300929201c2930208a164a23400929201c2920301a23500929201c2920302a23600929201c2920304a23700929201c2920308a23800929201c2920401a23900929201c2920402a23a00929201c2920404a23b00929201c2920408a23c0092930201a1439200c0a23d0092930201a1439201c3a23e0092930201a1439201c2a23f0092930201a143930201a143a2400092930201a143930201a163a2410092930201a143930202a153a2420092930201a143930202a173a2430092930201a143930204a14ca2440092930201a143930204a16ca2450092930201a143930208a171a2460092930201a143930204a166a2470092930201a143930208a164a2480092930201a143920301a2490092930201a143920302a24a0092930201a143920304a24b0092930201a143920308a24c0092930201a143920401a24d0092930201a143920402a24e0092930201a143920404a24f0092930201a143920408a2500092930201a1639200c0a2510092930201a1639201c3a2520092930201a1639201c2a2530092930201a163930201a143a2540092930201a163930201a163a2550092930201a163930202a153a2560092930201a163930202a173a2570092930201a163930204a14ca2580092930201a163930204a16ca2590092930201a163930208a171a25a0092930201a163930204a166a25b0092930201a163930208a164a25c0092930201a163920301a25d0092930201a163920302a25e0092930201a163920304a25f0092930201a163920308a2600092930201a163920401a2610092930201a163920402a2620092930201a163920404a2630092930201a163920408a2640092930202a1539200c0a2650092930202a1539201c3a2660092930202a1539201c2a2670092930202a153930201a143a2680092930202a153930201a163a2690092930202a153930202a153a26a0092930202a153930202a173a26b0092930202a153930204a14ca26c0092930202a153930204a16ca26d0092930202a153930208a171a26e0092930202a153930204a166a26f0092930202a153930208a164a2700092930202a153920301a2710092930202a153920302a2720092930202a153920304a2730092930202a153920308a2740092930202a153920401a2750092930202a153920402a2760092930202a153920404a2770092930202a153920408a2780092930202a1739200c0a2790092930202a1739201c3a27a0092930202a1739201c2a27b0092930202a173930201a143a27c0092930202a173930201a163a27d0092930202a173930202a153a27e0092930202a173930202a173a27f0092930202a173930204a14ca2800092930202a173930204a16ca2810092930202a173930208a171a2820092930202a173930204a166a2830092930202a173930208a164a2840092930202a173920301a2850092930202a173920302a2860092930202a173920304a2870092930202a173920308a2880092930202a173920401a2890092930202a173920402a28a0092930202a173920404a28b0092930202a173920408a28c0092930204a14c9200c0a28d0092930204a14c9201c3a28e0092930204a14c9201c2a28f0092930204a14c930201a143a2900092930204a14c930201a163a2910092930204a14c930202a153a2920092930204a14c930202a173a2930092930204a14c930204a14ca2940092930204a14c930204a16ca2950092930204a14c930208a171a2960092930204a14c930204a166a2970092930204a14c930208a164a2980092930204a14c920301a2990092930204a14c920302a29a0092930204a14c920304a29b0092930204a14c920308a29c0092930204a14c920401a29d0092930204a14c920402a29e0092930204a14c920404a29f0092930204a14c920408a2a00092930204a16c9200c0a2a10092930204a16c9201c3a2a20092930204a16c9201c2a2a30092930204a16c930201a143a2a40092930204a16c930201a163a2a50092930204a16c930202a153a2a60092930204a16c930202a173a2a70092930204a16c930204a14ca2a80092930204a16c930204a16ca2a90092930204a16c930208a171a2aa0092930204a16c930204a166a2ab0092930204a16c930208a164a2ac0092930204a16c920301a2ad0092930204a16c920302a2ae0092930204a16c920304a2af0092930204a16c920308a2b00092930204a16c920401a2b10092930204a16c920402a2b20092930204a16c920404a2b30092930204a16c920408a2b40092930208a1719200c0a2b50092930208a1719201c3a2b60092930208a1719201c2a2b70092930208a171930201a143a2b80092930208a171930201a163a2b90092930208a171930202a153a2ba0092930208a171930202a173a2bb0092930208a171930204a14ca2bc0092930208a171930204a16ca2bd0092930208a171930208a171a2be0092930208a171930204a166a2bf0092930208a171930208a164a2c00092930208a171920301a2c10092930208a171920302a2c20092930208a171920304a2c30092930208a171920308a2c40092930208a171920401a2c50092930208a171920402a2c60092930208a171920404a2c70092930208a171920408a2c80092930204a1669200c0a2c90092930204a1669201c3a2ca0092930204a1669201c2a2cb0092930204a166930201a143a2cc0092930204a166930201a163a2cd0092930204a166930202a153a2ce0092930204a166930202a173a2cf0092930204a166930204a14ca2d00092930204a166930204a16ca2d10092930204a166930208a171a2d20092930204a166930204a166a2d30092930204a166930208a164a2d40092930204a166920301a2d50092930204a166920302a2d60092930204a166920304a2d70092930204a166920308a2d80092930204a166920401a2d90092930204a166920402a2da0092930204a166920404a2db0092930204a166920408a2dc0092930208a1649200c0a2dd0092930208a1649201c3a2de0092930208a1649201c2a2df0092930208a164930201a143a2e00092930208a164930201a163a2e10092930208a164930202a153a2e20092930208a164930202a173a2e30092930208a164930204a14ca2e40092930208a164930204a16ca2e50092930208a164930208a171a2e60092930208a164930204a166a2e70092930208a164930208a164a2e80092930208a164920301a2e90092930208a164920302a2ea0092930208a164920304a2eb0092930208a164920308a2ec0092930208a164920401a2ed0092930208a164920402a2ee0092930208a164920404a2ef0092930208a164920408a2f000929203019200c0a2f100929203019201c3a2f200929203019201c2a2f30092920301930201a143a2f40092920301930201a163a2f50092920301930202a153a2f60092920301930202a173a2f70092920301930204a14ca2f80092920301930204a16ca2f90092920301930208a171a2fa0092920301930204a166a2fb0092920301930208a164a2fc0092920301920301a2fd0092920301920302a2fe0092920301920304a2ff0092920301920308a2000192920301920401a2010192920301920402a2020192920301920404a2030192920301920408a20401929203029200c0a20501929203029201c3a20601929203029201c2a2070192920302930201a143a2080192920302930201a163a2090192920302930202a153a20a0192920302930202a173a20b0192920302930204a14ca20c0192920302930204a16ca20d0192920302930208a171a20e0192920302930204a166a20f0192920302930208a164a2100192920302920301a2110192920302920302a2120192920302920304a2130192920302920308a2140192920302920401a2150192920302920402a2160192920302920404a2170192920302920408a21801929203049200c0a21901929203049201c3a21a01929203049201c2a21b0192920304930201a143a21c0192920304930201a163a21d0192920304930202a153a21e0192920304930202a173a21f0192920304930204a14ca2200192920304930204a16ca2210192920304930208a171a2220192920304930204a166a2230192920304930208a164a2240192920304920301a2250192920304920302a2260192920304920304a2270192920304920308a2280192920304920401a2290192920304920402a22a0192920304920404a22b0192920304920408a22c01929203089200c0a22d01929203089201c3a22e01929203089201c2a22f0192920308930201a143a2300192920308930201a163a2310192920308930202a153a2320192920308930202a173a2330192920308930204a14ca2340192920308930204a16ca2350192920308930208a171a2360192920308930204a166a2370192920308930208a164a2380192920308920301a2390192920308920302a23a0192920308920304a23b0192920308920308a23c0192920308920401a23d0192920308920402a23e0192920308920404a23f0192920308920408a24001929204019200c0a24101929204019201c3a24201929204019201c2a2430192920401930201a143a2440192920401930201a163a2450192920401930202a153a2460192920401930202a173a2470192920401930204a14ca2480192920401930204a16ca2490192920401930208a171a24a0192920401930204a166a24b0192920401930208a164a24c0192920401920301a24d0192920401920302a24e0192920401920304a24f0192920401920308a2500192920401920401a2510192920401920402a2520192920401920404a2530192920401920408a25401929204029200c0a25501929204029201c3a25601929204029201c2a2570192920402930201a143a2580192920402930201a163a2590192920402930202a153a25a0192920402930202a173a25b0192920402930204a14ca25c0192920402930204a16ca25d0192920402930208a171a25e0192920402930204a166a25f0192920402930208a164a2600192920402920301a2610192920402920302a2620192920402920304a2630192920402920308a2640192920402920401a2650192920402920402a2660192920402920404a2670192920402920408a26801929204049200c0a26901929204049201c3a26a01929204049201c2a26b0192920404930201a143a26c0192920404930201a163a26d0192920404930202a153a26e0192920404930202a173a26f0192920404930204a14ca2700192920404930204a16ca2710192920404930208a171a2720192920404930204a166a2730192920404930208a164a2740192920404920301a2750192920404920302a2760192920404920304a2770192920404920308a2780192920404920401a2790192920404920402a27a0192920404920404a27b0192920404920408a27c01929204089200c0a27d01929204089201c3a27e01929204089201c2a27f0192920408930201a143a2800192920408930201a163a2810192920408930202a153a2820192920408930202a173a2830192920408930204a14ca2840192920408930204a16ca2850192920408930208a171a2860192920408930204a166a2870192920408930208a164a2880192920408920301a2890192920408920302a28a0192920408920304a28b0192920408920308a28c0192920408920401a28d0192920408920402a28e0192920408920404a28f0192920408920408'));
    }

    private function completed()
    {
        $this->bufferClean();
        $this->completed = true;
        unset($this->bT, $this->bHL, $this->bDL);
    }
}