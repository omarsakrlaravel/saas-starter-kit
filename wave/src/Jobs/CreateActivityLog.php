<?php

namespace Wave\Jobs;

use App\Jobs\Middleware\EnsureAccountActive;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Wave\ActivityLog;

class CreateActivityLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! $this->isAllowedByAccountState()) {
            return;
        }

        ActivityLog::create($this->data);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureAccountActive];
    }

    private function isAllowedByAccountState(): bool
    {
        if (! isset($this->data['user_id'])) {
            return true;
        }

        $user = User::find($this->data['user_id']);
        if ($user instanceof User && $user->isBlockedFromSession()) {
            return false;
        }

        $organizationId = $this->data['organization_id'] ?? null;
        if (! $organizationId) {
            return true;
        }

        $organization = Organization::find($organizationId);
        if ($organization instanceof Organization && $organization->isBlockedFromSession()) {
            return false;
        }

        return true;
    }
}
