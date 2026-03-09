<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PhotoFrameResource\Pages;
use App\Models\PhotoFrame;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PhotoFrameResource extends Resource
{
    protected static ?string $model = PhotoFrame::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Photo Frames';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\FileUpload::make('frame_path')
                    ->label('Frame Image')
                    ->image()
                    ->disk('spaces')
                    ->directory('frames')
                    ->visibility('public')
                    ->required()
                    ->helperText('Upload the PNG frame overlay. The uploaded photo will be resized to fit this frame\'s dimensions.'),

                Forms\Components\Select::make('anchor_point')
                    ->label('Cover Anchor Point')
                    ->options(PhotoFrame::anchorPoints())
                    ->default('center')
                    ->required()
                    ->helperText('When applying the fill/cover mode, the photo will be cropped anchored to this position.'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Only one frame can be active at a time. Activating this will deactivate all others.')
                    ->reactive()
                    ->afterStateUpdated(function ($state, $record) {
                        if ($state && $record) {
                            PhotoFrame::where('id', '!=', $record->id)
                                ->update(['is_active' => false]);
                        }
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\ImageColumn::make('frame_path')
                    ->label('Frame Preview')
                    ->disk('spaces'),

                Tables\Columns\TextColumn::make('anchor_point')
                    ->label('Anchor Point')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPhotoFrames::route('/'),
            'create' => Pages\CreatePhotoFrame::route('/create'),
            'edit'   => Pages\EditPhotoFrame::route('/{record}/edit'),
        ];
    }
}
