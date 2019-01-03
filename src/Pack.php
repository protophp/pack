<?php

namespace Proto\Pack;

class Pack implements PackInterface
{
    use BufferTrait;

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

    public function getHeader()
    {
        return $this->header;
    }

    public function isHeader(): bool
    {
        return isset($this->header);
    }

    public function chunk(string $chunk)
    {
        if ($this->completed) {
            // TODO: Log warning error
            return;
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
        if ($return) return;

        // Parse HL
        if (!isset($this->bHL)) {
            $this->parseL($this->bT[0], $this->bHL, $return);
            if ($return) return;
        }

        // Parse DL
        if (!isset($this->bDL)) {
            $this->parseL($this->bT[1], $this->bDL, $return);
            if ($return) return;
        }

        // Parse HData
        if (!isset($this->header)) {
            $this->parseData($this->bT[0], $this->bHL, $this->header, $return);
            if ($return) return;
        }

        // Parse DData
        if (!isset($this->data)) {
            $this->parseData($this->bT[1], $this->bDL, $this->data, $return);
            if ($return) return;
        }

        $this->completed();
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
        if ($XT[0] === 0) {         // NULL & BOOL
            $XL = 0;

        } elseif ($XT[0] === 1) {   // INT & FLOAT & DOUBLE
            $XL = 0;

        } elseif ($XT[0] === 2 || $XT[0] === 3) {   // ARRAY & OBJECT & STRING
            if ($this->bufferLen() < $XT[1]) {
                $return = true;
                return;
            }

            $XL = unpack($this->intLength2unsignedPackCode($XT[1]), $this->bufferTrim($XT[1]));
        }
    }

    private function parseData(&$XT, &$XL, &$data, &$return)
    {
        if ($XT[0] === 0) {         // NULL & BOOL
            $data = $XT[1];

        } elseif ($XT[0] === 1) {   // INT & FLOAT & DOUBLE
            if ($this->bufferLen() < $XT[1]) {
                $return = true;
                return;
            }

            $data = unpack($XT[2], $this->bufferTrim($XT[1]));

        } elseif ($XT[0] === 2) {   // ARRAY & OBJECT
            if ($this->bufferLen() < $XL) {
                $return = true;
                return;
            }

            $data = msgpack_unpack($this->bufferTrim($XL));

        } elseif ($XT[0] === 3) {   // ARRAY & OBJECT
            if ($this->bufferLen() < $XL) {
                $return = true;
                return;
            }

            $data = $this->bufferTrim($XL);
        }
    }

    public function __toString(): string
    {
        if (isset($this->string))
            return $this->string;

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
        $this->ID2T();
        $this->prepare($this->header, $HT, $HL, $HData);
        $this->prepare($this->data, $DT, $DL, $DData);
        $T = array_search([$HT, $DT], self::$ID2T);

        $this->string = $T . $HL . $DL . $HData . $DData;
        return $this->string;
    }

    private function prepare($data, &$T, &$L, &$string)
    {
        if (is_null($data) || is_bool($data)) {
            $T = [0, $this->data];

        } elseif (is_string($data)) {
            $string = $data;
            $L = strlen($data);
            list($code, $l) = $this->int2pack($L);
            $T = [3, $l];
            $L = pack($code, $L);

        } elseif (is_array($data) || is_object($data)) {
            $string = msgpack_pack($data);
            $L = strlen($string);
            list($code, $l) = $this->int2pack($L);
            $T = [2, $l];
            $L = pack($code, $L);

        } elseif (is_int($data)) {
            list($code, $l) = $this->int2pack($data);
            $string = pack($code, $data);
            $T = [1, $l, $code];

        } elseif (is_float($data)) {
            $string = pack('f', $data);
            $T = [1, 4, 'f'];

        } elseif (is_double($this->data)) {
            $string = pack('d', $data);
            $T = [1, 8, 'd'];

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

            return ['Q', 8];

        } else {

            if ($int <= -0x80)
                return ['c', 1];

            if ($int <= -0x8000)
                return ['s', 2];

            if ($int <= -0x80000000)
                return ['l', 4];

            return ['q', 8];
        }
    }

    private function ID2T()
    {
        if (isset(self::$ID2T))
            return;

        self::$ID2T = msgpack_unpack(hex2bin('de00f0a100929200c09200c0a101929200c09200c3a102929200c09200c2a103929200c0930101a143a104929200c0930101a163a105929200c0930102a153a106929200c0930102a173a107929200c0930104a14ca108929200c0930104a16ca109929200c0930108a171a10a929200c0930104a166a10b929200c0930108a164a10c929200c0920201a10d929200c0920202a10e929200c0920204a10f929200c0920208a110929200c0920301a111929200c0920302a112929200c0920304a113929200c0920308a11492930101a1439200c0a11592930101a1439200c3a11692930101a1439200c2a11792930101a143930101a143a11892930101a143930101a163a11992930101a143930102a153a11a92930101a143930102a173a11b92930101a143930104a14ca11c92930101a143930104a16ca11d92930101a143930108a171a11e92930101a143930104a166a11f92930101a143930108a164a12092930101a143920201a12192930101a143920202a12292930101a143920204a12392930101a143920208a12492930101a143920301a12592930101a143920302a12692930101a143920304a12792930101a143920308a12892930101a1639200c0a12992930101a1639200c3a12a92930101a1639200c2a12b92930101a163930101a143a12c92930101a163930101a163a12d92930101a163930102a153a12e92930101a163930102a173a12f92930101a163930104a14c0092930101a163930104a16c0192930101a163930108a1710292930101a163930104a1660392930101a163930108a1640492930101a1639202010592930101a1639202020692930101a1639202040792930101a1639202080892930101a1639203010992930101a163920302a13a92930101a163920304a13b92930101a163920308a13c92930102a1539200c0a13d92930102a1539200c3a13e92930102a1539200c2a13f92930102a153930101a143a14092930102a153930101a163a14192930102a153930102a153a14292930102a153930102a173a14392930102a153930104a14ca14492930102a153930104a16ca14592930102a153930108a171a14692930102a153930104a166a14792930102a153930108a164a14892930102a153920201a14992930102a153920202a14a92930102a153920204a14b92930102a153920208a14c92930102a153920301a14d92930102a153920302a14e92930102a153920304a14f92930102a153920308a15092930102a1739200c0a15192930102a1739200c3a15292930102a1739200c2a15392930102a173930101a143a15492930102a173930101a163a15592930102a173930102a153a15692930102a173930102a173a15792930102a173930104a14ca15892930102a173930104a16ca15992930102a173930108a171a15a92930102a173930104a166a15b92930102a173930108a164a15c92930102a173920201a15d92930102a173920202a15e92930102a173920204a15f92930102a173920208a16092930102a173920301a16192930102a173920302a16292930102a173920304a16392930102a173920308a16492930104a14c9200c0a16592930104a14c9200c3a16692930104a14c9200c2a16792930104a14c930101a143a16892930104a14c930101a163a16992930104a14c930102a153a16a92930104a14c930102a173a16b92930104a14c930104a14ca16c92930104a14c930104a16ca16d92930104a14c930108a171a16e92930104a14c930104a166a16f92930104a14c930108a164a17092930104a14c920201a17192930104a14c920202a17292930104a14c920204a17392930104a14c920208a17492930104a14c920301a17592930104a14c920302a17692930104a14c920304a17792930104a14c920308a17892930104a16c9200c0a17992930104a16c9200c3a17a92930104a16c9200c2a17b92930104a16c930101a143a17c92930104a16c930101a163a17d92930104a16c930102a153a17e92930104a16c930102a173a17f92930104a16c930104a14ca18092930104a16c930104a16ca18192930104a16c930108a171a18292930104a16c930104a166a18392930104a16c930108a164a18492930104a16c920201a18592930104a16c920202a18692930104a16c920204a18792930104a16c920208a18892930104a16c920301a18992930104a16c920302a18a92930104a16c920304a18b92930104a16c920308a18c92930108a1719200c0a18d92930108a1719200c3a18e92930108a1719200c2a18f92930108a171930101a143a19092930108a171930101a163a19192930108a171930102a153a19292930108a171930102a173a19392930108a171930104a14ca19492930108a171930104a16ca19592930108a171930108a171a19692930108a171930104a166a19792930108a171930108a164a19892930108a171920201a19992930108a171920202a19a92930108a171920204a19b92930108a171920208a19c92930108a171920301a19d92930108a171920302a19e92930108a171920304a19f92930108a171920308a1a0929202019200c0a1a1929202019200c3a1a2929202019200c2a1a392920201930101a143a1a492920201930101a163a1a592920201930102a153a1a692920201930102a173a1a792920201930104a14ca1a892920201930104a16ca1a992920201930108a171a1aa92920201930104a166a1ab92920201930108a164a1ac92920201920201a1ad92920201920202a1ae92920201920204a1af92920201920208a1b092920201920301a1b192920201920302a1b292920201920304a1b392920201920308a1b4929202029200c0a1b5929202029200c3a1b6929202029200c2a1b792920202930101a143a1b892920202930101a163a1b992920202930102a153a1ba92920202930102a173a1bb92920202930104a14ca1bc92920202930104a16ca1bd92920202930108a171a1be92920202930104a166a1bf92920202930108a164a1c092920202920201a1c192920202920202a1c292920202920204a1c392920202920208a1c492920202920301a1c592920202920302a1c692920202920304a1c792920202920308a1c8929203019200c0a1c9929203019200c3a1ca929203019200c2a1cb92920301930101a143a1cc92920301930101a163a1cd92920301930102a153a1ce92920301930102a173a1cf92920301930104a14ca1d092920301930104a16ca1d192920301930108a171a1d292920301930104a166a1d392920301930108a164a1d492920301920201a1d592920301920202a1d692920301920204a1d792920301920208a1d892920301920301a1d992920301920302a1da92920301920304a1db92920301920308a1dc929203029200c0a1dd929203029200c3a1de929203029200c2a1df92920302930101a143a1e092920302930101a163a1e192920302930102a153a1e292920302930102a173a1e392920302930104a14ca1e492920302930104a16ca1e592920302930108a171a1e692920302930104a166a1e792920302930108a164a1e892920302920201a1e992920302920202a1ea92920302920204a1eb92920302920208a1ec92920302920301a1ed92920302920302a1ee92920302920304a1ef92920302920308'));
    }

    private function completed()
    {
        $this->bufferClean();
        $this->completed = true;
        unset($this->bT, $this->bHL, $this->bDL);
    }
}