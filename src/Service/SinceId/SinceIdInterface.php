<?php

namespace App\Service\SinceId;

interface SinceIdInterface
{
    public function save(): void;
    public function get(): ?int;
    public function set(int $sinceId);
}
