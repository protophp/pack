<?php

namespace Proto\Pack;

use Evenement\EventEmitter;

class Unpack extends EventEmitter implements UnpackInterface
{
    /**
     * @var PackInterface
     */
    private $merging = null;
    private $header = false;

    public function feed(string $chunk)
    {
        if (!isset($this->merging))
            $this->merging = new Pack();

        if ($this->merging->mergeFrom($chunk)) {
            $this->emit('unpack', [$this->merging]);
            $this->header = false;
            $this->merging = null;

        }

        if (!$this->header && $this->merging->isHeader()) {
            $this->emit('unpack-header', [$this->merging]);
            $this->header = true;
        }
    }

    public function merging(): PackInterface
    {
        return $this->merging;
    }
}