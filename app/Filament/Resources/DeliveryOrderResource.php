<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryOrderResource\Pages;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Transaksi Penjualan';
    protected static ?string $navigationLabel = 'Surat Jalan';
    protected static ?string $modelLabel = 'Surat Jalan';
    protected static ?string $pluralModelLabel = 'Surat Jalan';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('manage_delivery_orders');
    }
	
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi SJ')
                    ->schema([
                        Forms\Components\TextInput::make('do_number')
                            ->label('Nomor SJ')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'SJ-' . date('Ymd') . '-' . rand(1000, 9999))
                            ->maxLength(50),
                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('so_id')
                            ->label('Sales Order')
                            ->options(SalesOrder::whereIn('status', ['OPEN', 'PARTIAL'])->pluck('so_number', 'id'))
                            ->searchable()
                            ->required()
                            ->reactive()
							->default(fn () => request()->query('so_id'))
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $so = SalesOrder::with('details.product')->find($state);
                                if ($so) {
                                    $set('customer_id', $so->customer_id);
                                    $details = [];
                                    foreach ($so->details as $d) {
                                        if ($d->remaining_qty > 0) {
                                            $details[] = [
                                                'product_id' => $d->product_id,
                                                'max_qty' => $d->remaining_qty,
                                                'qty' => 0,
                                            ];
                                        }
                                    }
                                    $set('details', $details);
                                }
                            }),
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->options(Customer::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->disabled()
                            ->dehydrated(true),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'DRAFT' => 'Draft',
                                'SHIPPED' => 'Dikirim',
                                'DELIVERED' => 'Terkirim',
                                'CANCEL' => 'Batal',
                            ])
                            ->default('DRAFT')
                            ->required(),
                        Forms\Components\TextInput::make('driver')
                            ->label('Driver'),
                        Forms\Components\TextInput::make('vehicle')
                            ->label('Kendaraan'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Detail Barang')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship('details')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Barang')
                                    ->options(Product::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(true),
                                Forms\Components\TextInput::make('max_qty')
                                    ->label('Sisa Order')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('qty')
                                    ->label('Qty Kirim')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan'),
                            ])
                            ->columns(4)
                            ->addActionLabel('Tambah Barang'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('do_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('salesOrder.so_number')
                    ->label('SO'),
                Tables\Columns\TextColumn::make('customer.name'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'SHIPPED' => 'info',
                        'DELIVERED' => 'success',
                        'CANCEL' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('total_qty'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'SHIPPED' => 'Dikirim',
                        'DELIVERED' => 'Terkirim',
                        'CANCEL' => 'Batal',
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
            'index' => Pages\ListDeliveryOrders::route('/'),
            'create' => Pages\CreateDeliveryOrder::route('/create'),
            'edit' => Pages\EditDeliveryOrder::route('/{record}/edit'),
        ];
    }
}