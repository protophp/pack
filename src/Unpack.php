<?php

namespace Proto\Pack;

use Evenement\EventEmitter;

class Unpack extends EventEmitter implements UnpackInterface
{
    /**
     * @var PackInterface
     */
    private $merging = null;

    public function feed(string $chunk)
    {
        if (!isset($this->merging))
            $this->merging = new Pack();

        if ($this->merging->mergeFrom($chunk)) {
            $this->emit('unpack', [$this->merging]);
            $this->merging = null;
        }
    }

    public function merging(): PackInterface
    {
        return $this->merging;
    }
}