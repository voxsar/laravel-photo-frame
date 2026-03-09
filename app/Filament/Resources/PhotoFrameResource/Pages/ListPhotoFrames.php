<?php

namespace App\Filament\Resources\PhotoFrameResource\Pages;

use App\Filament\Resources\PhotoFrameResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPhotoFrames extends ListRecords
{
    protected static string $resource = PhotoFrameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
