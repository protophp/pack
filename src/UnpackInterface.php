<?php

namespace Proto\Pack;

use Evenement\EventEmitterInterface;

interface UnpackInterface extends EventEmitterInterface
{
    public function feed(string $chunk);

    public function merging(): PackInterface;

    public function clear();
}