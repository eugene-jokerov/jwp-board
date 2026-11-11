<?php

namespace App\Filament\Resources\Directions\Pages;

use App\Filament\Resources\Directions\DirectionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDirection extends ViewRecord
{
    protected static string $resource = DirectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
