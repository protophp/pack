<?php

namespace Proto\Pack;

class PackException extends \Exception
{
    const ERR_HEADER_UNSUPPORTED_TYPES = 100;
    const ERR_HEADER_TOO_LARGE = 110;
}