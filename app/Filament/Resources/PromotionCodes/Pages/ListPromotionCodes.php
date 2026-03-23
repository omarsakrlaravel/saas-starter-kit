<?php

namespace App\Filament\Resources\PromotionCodes\Pages;

use App\Filament\Resources\PromotionCodes\PromotionCodeResource;
use Filament\Resources\Pages\ListRecords;

class ListPromotionCodes extends ListRecords
{
    protected static string $resource = PromotionCodeResource::class;
}
