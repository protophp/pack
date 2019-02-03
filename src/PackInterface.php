<?php

namespace Proto\Pack;

interface PackInterface
{
    const OPT_HEADER_LIMITED = 1;

    public function setData($data): PackInterface;

    public function getData();

    public function isData(): bool;

    public function setHeader($header): PackInterface;

    public function setHeaderByKey($key, $value): PackInterface;

    public function getHeader();

    public function getHeaderByKey($key, $default = null);

    public function isHeader(): bool;

    public function getMergingProgress(): int;

    public function mergeFrom(string $chunk);

    public function isMerged(): bool;

    public function toString(): string;
}