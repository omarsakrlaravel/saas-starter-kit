<?php

namespace Wave\Traits;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wave\Scopes\TenantScope;
use Wave\TenantContext;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->has() && is_null($model->organization_id)) {
                $model->organization_id = $context->get();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
