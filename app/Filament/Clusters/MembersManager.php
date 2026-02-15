<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class MembersManager extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'phosphor-users-duotone';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;
}
