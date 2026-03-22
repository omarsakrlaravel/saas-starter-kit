<?php

namespace App\Services;

class TenantContext
{
    protected ?int $organizationId = null;

    public function set(?int $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function get(): ?int
    {
        return $this->organizationId;
    }

    public function has(): bool
    {
        return $this->organizationId !== null;
    }
}
