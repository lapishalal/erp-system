<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanySettingResource\Pages;
use App\Models\CompanySetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanySettingResource extends Resource
{
    protected static ?string $model = CompanySetting::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Data Perusahaan';
    protected static ?string $modelLabel = 'Data Perusahaan';
    protected static ?string $pluralModelLabel = 'Data Perusahaan';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('manage_master_data');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Perusahaan')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->label('Nama Perusahaan')
                            ->required(),
                        Forms\Components\Textarea::make('address')
                            ->label('Alamat')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Telepon'),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email(),
                    ])->columns(2),

                Forms\Components\Section::make('Logo')
                    ->schema([
                        Forms\Components\FileUpload::make('logo')
                            ->label('Logo Perusahaan')
                            ->image()
                            ->directory('company')
                            ->maxSize(2048),
                    ]),

                Forms\Components\Section::make('Tanda Tangan & Stampel')
                    ->schema([
                        Forms\Components\TextInput::make('signature_name')
                            ->label('Nama Penanda Tangan'),
                        Forms\Components\FileUpload::make('signature_image')
                            ->label('Tanda Tangan (Image)')
                            ->image()
                            ->directory('company')
                            ->maxSize(1024),
                        Forms\Components\FileUpload::make('stamp_image')
                            ->label('Stampel (Image)')
                            ->image()
                            ->directory('company')
                            ->maxSize(1024),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('email'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanySettings::route('/'),
            'create' => Pages\CreateCompanySetting::route('/create'),
            'edit' => Pages\EditCompanySetting::route('/{record}/edit'),
        ];
    }
}