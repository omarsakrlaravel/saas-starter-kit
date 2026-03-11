<?php

namespace App\Enums;

enum FeatureFlagType: string
{
    case PlanGated = 'plan_gated';

    case Rollout = 'rollout';

    case KillSwitch = 'kill_switch';

    public function label(): string
    {
        return match ($this) {
            self::PlanGated => 'Plan-Gated',
            self::Rollout => 'Rollout',
            self::KillSwitch => 'Kill Switch',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PlanGated => 'info',
            self::Rollout => 'warning',
            self::KillSwitch => 'danger',
        };
    }
}
