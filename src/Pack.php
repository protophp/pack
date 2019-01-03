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

        self::$ID2T = msgpack_unpack('� � �� �� ���� �� á�� �� ¡�� ���C��� ���c��� ���S��� ���s��� ���L��� ���l�	�� ���q�
�� ���f��� ���d��� ���
�� ����� ����� ����� ����� ����� ����� ������C� �����C� á���C� ¡���C��C����C��c����C��S����C��s����C��L����C��l����C��q����C��f����C��d� ���C��!���C��"���C��#���C��$���C��%���C��&���C��\'���C��(���c� ��)���c� á*���c� ¡+���c��C�,���c��c�-���c��S�.���c��s�/���c��L ���c��l���c��q���c��f���c��d���c����c����c����c����c�	���c��:���c��;���c��<���S� ��=���S� á>���S� ¡?���S��C�@���S��c�A���S��S�B���S��s�C���S��L�D���S��l�E���S��q�F���S��f�G���S��d�H���S��I���S��J���S��K���S��L���S��M���S��N���S��O���S��P���s� ��Q���s� áR���s� ¡S���s��C�T���s��c�U���s��S�V���s��s�W���s��L�X���s��l�Y���s��q�Z���s��f�[���s��d�\���s��]���s��^���s��_���s��`���s��a���s��b���s��c���s��d���L� ��e���L� áf���L� ¡g���L��C�h���L��c�i���L��S�j���L��s�k���L��L�l���L��l�m���L��q�n���L��f�o���L��d�p���L��q���L��r���L��s���L��t���L��u���L��v���L��w���L��x���l� ��y���l� áz���l� ¡{���l��C�|���l��c�}���l��S�~���l��s����l��L�����l��l�����l��q�����l��f�����l��d�����l������l������l������l������l������l������l������l������q� ������q� á����q� ¡����q��C�����q��c�����q��S�����q��s�����q��L�����q��l�����q��q�����q��f�����q��d�����q������q������q������q������q������q������q������q������ ������ á���� ¡�����C������c������S������s������L������l������q������f������d��������������������������������������������� ������ á���� ¡�����C������c������S������s������L������l������q������f������d��������������Ò���Ē���Œ���ƒ���ǒ���Ȓ�� ��ɒ�� áʒ�� ¡˒���C�̒���c�͒���S�Β���s�ϒ���L�В���l�ђ���q�Ғ���f�Ӓ���d�Ԓ���Ւ���֒���ג���ؒ���ْ���ڒ���ے���ܒ�� ��ݒ�� áޒ�� ¡ߒ���C������c�ᒒ��S�⒒��s�㒒��L�䒒��l�咒��q�撒��f�璒��d�蒒��钒��꒒��뒒��쒒��풒�����');
    }

    private function completed()
    {
        $this->bufferClean();
        $this->completed = true;
        unset($this->bT, $this->bHL, $this->bDL);
    }
}