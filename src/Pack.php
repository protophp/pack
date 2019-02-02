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

    private $mergingProgress = 0;
    private $completed = false;

    private static $ID2T;

    public function __construct()
    {
        // Build static ID2T
        $this->ID2T();
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

    public function setHeaderByKey($key, $value): PackInterface
    {
        $this->header[$key] = $value;
        return $this;
    }

    public function getHeader()
    {
        return $this->header;
    }

    public function getHeaderByKey($key, $default = null)
    {
        return isset($this->header[$key]) ? $this->header[$key] : $default;
    }

    public function isHeader(): bool
    {
        return isset($this->header);
    }

    public function getMergingProgress(): int
    {
        return $this->mergingProgress;
    }

    public function mergeFrom(string $chunk)
    {
        if ($this->completed) {
            // TODO: Log warning error
            return false;
        }

        $this->mergingProgress += strlen($chunk);
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

        $restBuffer = $this->buffer;
        $this->completed();
        return $restBuffer;
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

        // Prepare Header
        $this->prepare($this->header, $HT, $HL, $HData);

        // Header limited mode
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

        self::$ID2T = msgpack_unpack(hex2bin('de00f0a100929200c09200c0a101929200c09201c3a102929200c09201c2a103929200c0930201a143a104929200c0930201a163a105929200c0930202a153a106929200c0930202a173a107929200c0930204a14ca108929200c0930204a16ca109929200c0930208a171a10a929200c0930204a166a10b929200c0930208a164a10c929200c0920301a10d929200c0920302a10e929200c0920304a10f929200c0920308a110929200c0920401a111929200c0920402a112929200c0920404a113929200c0920408a11492930201a1439200c0a11592930201a1439201c3a11692930201a1439201c2a11792930201a143930201a143a11892930201a143930201a163a11992930201a143930202a153a11a92930201a143930202a173a11b92930201a143930204a14ca11c92930201a143930204a16ca11d92930201a143930208a171a11e92930201a143930204a166a11f92930201a143930208a164a12092930201a143920301a12192930201a143920302a12292930201a143920304a12392930201a143920308a12492930201a143920401a12592930201a143920402a12692930201a143920404a12792930201a143920408a12892930201a1639200c0a12992930201a1639201c3a12a92930201a1639201c2a12b92930201a163930201a143a12c92930201a163930201a163a12d92930201a163930202a153a12e92930201a163930202a173a12f92930201a163930204a14c0092930201a163930204a16c0192930201a163930208a1710292930201a163930204a1660392930201a163930208a1640492930201a1639203010592930201a1639203020692930201a1639203040792930201a1639203080892930201a1639204010992930201a163920402a13a92930201a163920404a13b92930201a163920408a13c92930202a1539200c0a13d92930202a1539201c3a13e92930202a1539201c2a13f92930202a153930201a143a14092930202a153930201a163a14192930202a153930202a153a14292930202a153930202a173a14392930202a153930204a14ca14492930202a153930204a16ca14592930202a153930208a171a14692930202a153930204a166a14792930202a153930208a164a14892930202a153920301a14992930202a153920302a14a92930202a153920304a14b92930202a153920308a14c92930202a153920401a14d92930202a153920402a14e92930202a153920404a14f92930202a153920408a15092930202a1739200c0a15192930202a1739201c3a15292930202a1739201c2a15392930202a173930201a143a15492930202a173930201a163a15592930202a173930202a153a15692930202a173930202a173a15792930202a173930204a14ca15892930202a173930204a16ca15992930202a173930208a171a15a92930202a173930204a166a15b92930202a173930208a164a15c92930202a173920301a15d92930202a173920302a15e92930202a173920304a15f92930202a173920308a16092930202a173920401a16192930202a173920402a16292930202a173920404a16392930202a173920408a16492930204a14c9200c0a16592930204a14c9201c3a16692930204a14c9201c2a16792930204a14c930201a143a16892930204a14c930201a163a16992930204a14c930202a153a16a92930204a14c930202a173a16b92930204a14c930204a14ca16c92930204a14c930204a16ca16d92930204a14c930208a171a16e92930204a14c930204a166a16f92930204a14c930208a164a17092930204a14c920301a17192930204a14c920302a17292930204a14c920304a17392930204a14c920308a17492930204a14c920401a17592930204a14c920402a17692930204a14c920404a17792930204a14c920408a17892930204a16c9200c0a17992930204a16c9201c3a17a92930204a16c9201c2a17b92930204a16c930201a143a17c92930204a16c930201a163a17d92930204a16c930202a153a17e92930204a16c930202a173a17f92930204a16c930204a14ca18092930204a16c930204a16ca18192930204a16c930208a171a18292930204a16c930204a166a18392930204a16c930208a164a18492930204a16c920301a18592930204a16c920302a18692930204a16c920304a18792930204a16c920308a18892930204a16c920401a18992930204a16c920402a18a92930204a16c920404a18b92930204a16c920408a18c92930208a1719200c0a18d92930208a1719201c3a18e92930208a1719201c2a18f92930208a171930201a143a19092930208a171930201a163a19192930208a171930202a153a19292930208a171930202a173a19392930208a171930204a14ca19492930208a171930204a16ca19592930208a171930208a171a19692930208a171930204a166a19792930208a171930208a164a19892930208a171920301a19992930208a171920302a19a92930208a171920304a19b92930208a171920308a19c92930208a171920401a19d92930208a171920402a19e92930208a171920404a19f92930208a171920408a1a0929203019200c0a1a1929203019201c3a1a2929203019201c2a1a392920301930201a143a1a492920301930201a163a1a592920301930202a153a1a692920301930202a173a1a792920301930204a14ca1a892920301930204a16ca1a992920301930208a171a1aa92920301930204a166a1ab92920301930208a164a1ac92920301920301a1ad92920301920302a1ae92920301920304a1af92920301920308a1b092920301920401a1b192920301920402a1b292920301920404a1b392920301920408a1b4929203029200c0a1b5929203029201c3a1b6929203029201c2a1b792920302930201a143a1b892920302930201a163a1b992920302930202a153a1ba92920302930202a173a1bb92920302930204a14ca1bc92920302930204a16ca1bd92920302930208a171a1be92920302930204a166a1bf92920302930208a164a1c092920302920301a1c192920302920302a1c292920302920304a1c392920302920308a1c492920302920401a1c592920302920402a1c692920302920404a1c792920302920408a1c8929204019200c0a1c9929204019201c3a1ca929204019201c2a1cb92920401930201a143a1cc92920401930201a163a1cd92920401930202a153a1ce92920401930202a173a1cf92920401930204a14ca1d092920401930204a16ca1d192920401930208a171a1d292920401930204a166a1d392920401930208a164a1d492920401920301a1d592920401920302a1d692920401920304a1d792920401920308a1d892920401920401a1d992920401920402a1da92920401920404a1db92920401920408a1dc929204029200c0a1dd929204029201c3a1de929204029201c2a1df92920402930201a143a1e092920402930201a163a1e192920402930202a153a1e292920402930202a173a1e392920402930204a14ca1e492920402930204a16ca1e592920402930208a171a1e692920402930204a166a1e792920402930208a164a1e892920402920301a1e992920402920302a1ea92920402920304a1eb92920402920308a1ec92920402920401a1ed92920402920402a1ee92920402920404a1ef92920402920408'));
    }

    private function completed()
    {
        $this->bufferClean();
        $this->completed = true;
        unset($this->bT, $this->bHL, $this->bDL);
    }
}