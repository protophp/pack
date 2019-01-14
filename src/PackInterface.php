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

    public function getHeaderByKey($key);

    public function isHeader(): bool;

    public function mergeFrom(string $chunk): bool;

    public function toString(): string;
}