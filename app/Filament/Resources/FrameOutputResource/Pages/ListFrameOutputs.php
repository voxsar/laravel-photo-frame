<?php

namespace App\Filament\Resources\FrameOutputResource\Pages;

use App\Filament\Resources\FrameOutputResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFrameOutputs extends ListRecords
{
    protected static string $resource = FrameOutputResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
