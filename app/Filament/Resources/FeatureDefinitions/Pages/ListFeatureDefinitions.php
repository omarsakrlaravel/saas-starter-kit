<?php

namespace App\Filament\Resources\FeatureDefinitions\Pages;

use App\Filament\Resources\FeatureDefinitions\FeatureDefinitionResource;
use App\Filament\Widgets\KillSwitchesWidget;
use Filament\Resources\Pages\ListRecords;

class ListFeatureDefinitions extends ListRecords
{
    protected static string $resource = FeatureDefinitionResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            KillSwitchesWidget::class,
        ];
    }
}
