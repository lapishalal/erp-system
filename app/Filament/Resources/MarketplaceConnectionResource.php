<?php

namespace App\Filament\Resources;

use App\Enums\MarketplacePlatform;
use App\Filament\Resources\MarketplaceConnectionResource\Pages;
use App\Models\MarketplaceConnection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceConnectionResource extends Resource
{
    protected static ?string $model = MarketplaceConnection::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Marketplace API';
    protected static ?string $modelLabel = 'Koneksi Marketplace';
    protected static ?string $pluralModelLabel = 'Koneksi Marketplace';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 99;
    
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('manage_products');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Forms\Components\Select::make('platform')
                            ->label('Platform')
                            ->options([
                                'tiktok' => 'TikTok Shop',
                                'shopee' => 'Shopee',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('shop_name')
                            ->label('Nama Toko')
                            ->placeholder('Contoh: Toko Saya Official')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('shop_id')
                            ->label('Shop ID')
                            ->placeholder('Contoh: 12345678')
                            ->required()
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Kredensial API')
                    ->description('Data ini didapat saat daftar API di Seller Center')
                    ->schema([
                        Forms\Components\TextInput::make('app_key')
                            ->label('App Key / Client ID')
                            ->placeholder('Masukkan App Key dari TikTok/Shopee Developer')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('app_secret')
                            ->label('App Secret / Client Secret')
                            ->placeholder('Masukkan App Secret')
                            ->required()
                            ->rows(2),

                        Forms\Components\Textarea::make('access_token')
                            ->label('Access Token')
                            ->placeholder('Masukkan Access Token (didapat setelah oAuth)')
                            ->required()
                            ->rows(2),

                        Forms\Components\Textarea::make('refresh_token')
                            ->label('Refresh Token')
                            ->placeholder('Masukkan Refresh Token')
                            ->rows(2),
                    ]),

                Forms\Components\Section::make('Pengaturan')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan jika tidak ingin sinkronisasi'),

                        Forms\Components\KeyValue::make('settings')
                            ->label('Pengaturan Tambahan')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('Opsional. Gunakan untuk pengaturan khusus platform.')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tiktok' => 'danger',
                        'shopee' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'tiktok' => 'TikTok Shop',
                        'shopee' => 'Shopee',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('shop_name')
                    ->label('Nama Toko')
                    ->searchable(),

                Tables\Columns\TextColumn::make('shop_id')
                    ->label('Shop ID')
                    ->copyable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Update')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->options([
                        'tiktok' => 'TikTok Shop',
                        'shopee' => 'Shopee',
                    ]),
            ])
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketplaceConnections::route('/'),
            'create' => Pages\CreateMarketplaceConnection::route('/create'),
            'edit' => Pages\EditMarketplaceConnection::route('/{record}/edit'),
        ];
    }
}