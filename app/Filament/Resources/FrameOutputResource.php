<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrameOutputResource\Pages;
use App\Filament\Resources\FrameOutputResource\RelationManagers;
use App\Models\FrameOutput;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class FrameOutputResource extends Resource
{
    protected static ?string $model = FrameOutput::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //photo_frame_id,original_filename,fill_path,contain_path
				Forms\Components\TextInput::make('original_filename')
					->label('Original Filename')
					->disabled(),

				Forms\Components\TextInput::make('fill_path')
					->label('Fill Image URL')
					->disabled()
					->helperText('URL to the processed image in fill/cover mode.'),

                Forms\Components\Placeholder::make('fill_preview')
                    ->label('Fill Preview')
                    ->content(function (?FrameOutput $record): HtmlString {
                        if (! $record?->fill_path) {
                            return new HtmlString('<span style="color:#6b7280;">No image available</span>');
                        }

                        $url = e(Storage::disk('spaces')->url($record->fill_path));

                        return new HtmlString('<img src="' . $url . '" alt="Fill Preview" style="max-width: 220px; border-radius: 8px;" />');
                    }),

				Forms\Components\TextInput::make('contain_path')
					->label('Contain Image URL')
					->disabled()
					->helperText('URL to the processed image in contain/fit mode.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
				Tables\Columns\TextColumn::make('id')->sortable(),
				//preview ciricle
				Tables\Columns\ImageColumn::make('fill_path')
					->label('Fill Preview')
					->disk('spaces')
					->circular(),
				Tables\Columns\TextColumn::make('photoFrame.name')->label('Photo Frame')->sortable()->searchable(),
				Tables\Columns\TextColumn::make('original_filename')->sortable()->searchable(),
				Tables\Columns\TextColumn::make('created_at')->label('Created')
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrameOutputs::route('/'),
            'create' => Pages\CreateFrameOutput::route('/create'),
            'edit' => Pages\EditFrameOutput::route('/{record}/edit'),
        ];
    }
}
