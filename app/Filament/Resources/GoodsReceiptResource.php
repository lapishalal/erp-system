<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoodsReceiptResource\Pages;
use App\Models\CompanySetting;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Barryvdh\DomPDF\Facade\Pdf;

class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Transaksi Pembelian';
    protected static ?string $label = 'Penerimaan Barang';
    protected static ?string $pluralLabel = 'Penerimaan Barang';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('manage_goods_receipts');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi GR')
                    ->schema([
                        Forms\Components\TextInput::make('gr_number')
                            ->label('Nomor GR')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'GR-' . date('Ymd') . '-' . rand(1000, 9999))
                            ->maxLength(50),
                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('po_id')
                            ->label('Purchase Order')
                            ->options(function () {
                                return PurchaseOrder::whereNotIn('status', ['COMPLETE', 'CANCEL'])
                                    ->pluck('po_number', 'id');
                            })
                            ->searchable()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (!$state) {
                                    $set('supplier_id', null);
                                    $set('details', []);
                                    return;
                                }
                                $po = PurchaseOrder::with(['details.product', 'supplier'])->find($state);
                                if (!$po) {
                                    $set('supplier_id', null);
                                    $set('details', []);
                                    return;
                                }
                                $set('supplier_id', $po->supplier_id);
                                $details = [];
                                foreach ($po->details as $d) {
                                    $sisa = $d->remaining_qty ?? max(0, $d->qty - ($d->received_qty ?? 0));
                                    if ($sisa > 0) {
                                        $details[] = [
                                            'product_id' => $d->product_id,
                                            'buy_price' => $d->unit_price,
                                            'qty' => $sisa,
                                        ];
                                    }
                                }
                                $set('details', $details);
                            })
                            ->placeholder('Kosongkan jika barang tidak dari PO'),
                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(Supplier::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->disabled(fn (Forms\Get $get) => filled($get('po_id')))
                            ->dehydrated(true),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('Gudang Tujuan')
                            ->options(Warehouse::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->default(fn () => Warehouse::first()?->id),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'DRAFT' => 'Draft',
                                'RECEIVED' => 'Diterima',
                                'CANCEL' => 'Batal',
                            ])
                            ->default('DRAFT')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Referensi Purchase Order')
                    ->visible(fn (Forms\Get $get) => filled($get('po_id')))
                    ->schema([
                        Forms\Components\Placeholder::make('po_reference')
                            ->label(false)
                            ->content(function (Forms\Get $get) {
                                $po = PurchaseOrder::with(['details.product', 'supplier'])->find($get('po_id'));
                                if (!$po) {
                                    return new \Illuminate\Support\HtmlString('<p class="text-gray-500">PO tidak ditemukan</p>');
                                }
                                $html = '<div class="mb-2 text-sm font-semibold text-gray-700">'
                                    . 'PO: ' . e($po->po_number) . ' | Supplier: ' . e($po->supplier?->name ?? '-') . ' | Status: ' . e($po->status)
                                    . '</div>'
                                    . '<table class="w-full text-sm border rounded-lg overflow-hidden">'
                                    . '<thead class="bg-gray-100 text-gray-600">'
                                    . '<tr>'
                                    . '<th class="px-3 py-2 text-left">Barang</th>'
                                    . '<th class="px-3 py-2 text-center">Qty Order</th>'
                                    . '<th class="px-3 py-2 text-center">Sudah Terima</th>'
                                    . '<th class="px-3 py-2 text-center">Sisa</th>'
                                    . '<th class="px-3 py-2 text-right">Harga Beli</th>'
                                    . '</tr></thead><tbody class="divide-y">';
                                foreach ($po->details as $d) {
                                    $sisa = $d->remaining_qty ?? max(0, $d->qty - ($d->received_qty ?? 0));
                                    $sisaClass = $sisa > 0 ? 'text-primary-600 font-bold' : 'text-success-600';
                                    $html .= '<tr>'
                                        . '<td class="px-3 py-2">' . e($d->product?->name ?? '-') . '</td>'
                                        . '<td class="px-3 py-2 text-center">' . $d->qty . '</td>'
                                        . '<td class="px-3 py-2 text-center">' . ($d->received_qty ?? 0) . '</td>'
                                        . '<td class="px-3 py-2 text-center ' . $sisaClass . '">' . $sisa . '</td>'
                                        . '<td class="px-3 py-2 text-right">Rp ' . number_format($d->unit_price, 0, ',', '.') . '</td>'
                                        . '</tr>';
                                }
                                $html .= '</tbody></table>'
                                    . '<p class="mt-2 text-xs text-gray-500">'
                                    . '* Qty Terima di bawah sudah diisi default = sisa PO. Silakan edit jika tidak semua barang datang.'
                                    . '</p>';
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Detail Barang Datang')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship('details')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Barang')
                                    ->options(Product::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->disabled(fn (Forms\Get $get) => filled($get('po_id')))
                                    ->dehydrated(true),
                                Forms\Components\TextInput::make('buy_price')
                                    ->label('Harga Beli')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->disabled(fn (Forms\Get $get) => filled($get('po_id')))
                                    ->dehydrated(true),
                                Forms\Components\TextInput::make('qty')
                                    ->label('Qty Terima')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated(true),
                            ])
                            ->columns(4)
                            ->addable(fn (Forms\Get $get) => empty($get('po_id')))
                            ->deletable(fn (Forms\Get $get) => empty($get('po_id')))
                            ->reorderable(false),
                    ]),

                Forms\Components\Section::make('Ringkasan')
                    ->schema([
                        Forms\Components\TextInput::make('total_qty')
                            ->label('Total Qty')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(true),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Nilai')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gr_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')
                    ->label('PO')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('supplier.name'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'RECEIVED' => 'success',
                        'CANCEL' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('total_qty'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('IDR'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'RECEIVED' => 'Diterima',
                        'CANCEL' => 'Batal',
                    ]),
                Tables\Filters\SelectFilter::make('po_id')
                    ->label('Purchase Order')
                    ->relationship('purchaseOrder', 'po_number')
                    ->searchable()
                    ->preload()
                    ->placeholder('Semua (termasuk tanpa PO)'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('printPdf')
                        ->label('Print PDF')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->action(function (GoodsReceipt $record) {
                            $company = CompanySetting::first();
                            $pdf = Pdf::loadView('pdf.gr', ['gr' => $record->load('details.product', 'purchaseOrder', 'supplier', 'warehouse', 'creator'), 'company' => $company]);
                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->output();
                            }, 'GR-' . $record->gr_number . '.pdf');
                        }),

                    Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListGoodsReceipts::route('/'),
            'create' => Pages\CreateGoodsReceipt::route('/create'),
            'edit' => Pages\EditGoodsReceipt::route('/{record}/edit'),
        ];
    }
}