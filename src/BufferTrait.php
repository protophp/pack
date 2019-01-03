<?php

namespace Proto\Pack;

trait BufferTrait
{
    private $buffer = '';

    /**
     * Get buffer's length
     * @return int
     */
    private function bufferLen(): int
    {
        return strlen($this->buffer);
    }

    /**
     * Trim buffer from begin and return data.
     * Buffer will be updated.
     *
     * @param int $length
     * @return bool|string
     */
    private function bufferTrim(int $length): string
    {
        $data = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);
        return $data;
    }

    /**
     * Clean buffer
     */
    private function bufferClean()
    {
        $this->buffer = '';
    }
}