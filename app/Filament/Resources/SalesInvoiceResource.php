<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Transaksi Penjualan';
    protected static ?string $navigationLabel = 'Faktur Penjualan';
    protected static ?string $modelLabel = 'Faktur Penjualan';
    protected static ?string $pluralModelLabel = 'Faktur Penjualan';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('manage_sales_orders');
    }
	
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Faktur')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nomor Faktur')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'INV-' . date('Ymd') . '-' . rand(1000, 9999))
                            ->maxLength(50),

                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Jatuh Tempo')
                            ->required()
                            ->default(now()->addDays(30)),

                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->options(Customer::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('so_id')
                            ->label('Sales Order (Opsional)')
                            ->options(SalesOrder::whereNotIn('status', ['DRAFT', 'CANCEL'])->pluck('so_number', 'id'))
                            ->searchable()
                            ->live()
                            ->default(fn () => request()->query('so_id'))
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $so = SalesOrder::with('details.product')->find($state);
                                    if ($so) {
                                        $set('customer_id', $so->customer_id);
                                        $details = [];
                                        foreach ($so->details as $d) {
                                            if ($d->remaining_qty > 0) {
                                                $details[] = [
                                                    'product_id' => $d->product_id,
                                                    'qty' => $d->remaining_qty,
                                                    'price' => $d->unit_price,
                                                    'subtotal' => $d->remaining_qty * $d->unit_price,
                                                ];
                                            }
                                        }
                                        $set('details', $details);
                                    }
                                }
                            })
                            ->placeholder('Kosongkan jika faktur tidak dari SO'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'UNPAID' => 'Belum Bayar',
                                'PARTIAL' => 'Partial Bayar',
                                'PAID' => 'Lunas',
                                'OVERDUE' => 'Jatuh Tempo',
                            ])
                            ->default('UNPAID')
                            ->required(),

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
                                    ->required(),

                                Forms\Components\TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, Forms\Set $set) {
                                        $price = $get('price') ?? 0;
                                        $set('subtotal', ($state ?? 0) * $price);
                                    }),

                                Forms\Components\TextInput::make('price')
                                    ->label('Harga Satuan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, Forms\Set $set) {
                                        $qty = $get('qty') ?? 0;
                                        $set('subtotal', $qty * ($state ?? 0));
                                    }),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated(true),
                            ])
                            ->columns(4)
                            ->addActionLabel('Tambah Barang')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $total = 0;
                                foreach ($state ?? [] as $item) {
                                    $total += ($item['qty'] ?? 0) * ($item['price'] ?? 0);
                                }
                                $set('total', $total);
                                $set('paid_amount', 0);
                            }),
                    ]),

                Forms\Components\Section::make('Pembayaran')
                    ->schema([
                        Forms\Components\TextInput::make('total')
                            ->label('Total Faktur')
							->numeric()
							->prefix('Rp')
							->default(0)
							->disabled()
							->dehydrated(true),

                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Sudah Dibayar')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                $total = $get('total') ?? 0;
                                $paid = $state ?? 0;
                                if ($paid >= $total && $total > 0) {
                                    $set('status', 'PAID');
                                } elseif ($paid > 0 && $paid < $total) {
                                    $set('status', 'PARTIAL');
                                } else {
                                    $set('status', 'UNPAID');
                                }
                            }),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date('d M Y')
                    ->label('Jatuh Tempo'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('salesOrder.so_number')
                    ->label('SO')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('total')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->money('IDR')
                    ->label('Dibayar'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'UNPAID' => 'warning',
                        'PARTIAL' => 'info',
                        'PAID' => 'success',
                        'OVERDUE' => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'UNPAID' => 'Belum Bayar',
                        'PARTIAL' => 'Partial',
                        'PAID' => 'Lunas',
                        'OVERDUE' => 'Jatuh Tempo',
                    ]),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('print')
                        ->label('Print PDF')
                        ->icon('heroicon-o-printer')
                        ->url(fn (SalesInvoice $record): string => url('/invoice/' . $record->id . '/print'))
                        ->openUrlInNewTab(),
                    Tables\Actions\DeleteAction::make(),
                ]),
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
            'index' => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'edit' => Pages\EditSalesInvoice::route('/{record}/edit'),
            'view' => Pages\ViewSalesInvoice::route('/{record}'),
        ];
    }
}