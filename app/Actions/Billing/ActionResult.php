<?php

namespace App\Actions\Billing;

class ActionResult
{
    public function __construct(
        public bool $success,
        public string $message,
    ) {}

    public static function ok(string $message): self
    {
        return new self(true, $message);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
