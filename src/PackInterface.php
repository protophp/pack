<?php

namespace Proto\Pack;

interface PackInterface
{
    public function setData($data): PackInterface;

    public function getData();

    public function isData(): bool;

    public function setHeader($header): PackInterface;

    public function getHeader();

    public function isHeader(): bool;

    public function chunk(string $chunk);

    public function __toString(): string;
}