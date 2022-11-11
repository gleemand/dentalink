<?php

namespace App\Service\SinceDateTime;

interface SinceDateTimeInterface
{
    public function save(): void;
    public function get(): string;
    public function set(): void;
}
