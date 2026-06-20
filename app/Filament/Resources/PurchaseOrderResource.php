<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\CompanySetting;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Transaksi Pembelian';
    protected static ?string $navigationLabel = 'Purchase Order';
    protected static ?string $modelLabel = 'Purchase Order';
    protected static ?string $pluralModelLabel = 'Purchase Order';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('manage_purchase_orders');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi PO')
                    ->schema([
                        Forms\Components\TextInput::make('po_number')
                            ->label('Nomor PO')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'PO-' . date('Ymd') . '-' . rand(1000, 9999))
                            ->maxLength(50),
                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(Supplier::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'DRAFT' => 'Draft',
                                'ORDERED' => 'Ordered',
                                'PARTIAL' => 'Partial',
                                'COMPLETE' => 'Complete',
                                'CANCEL' => 'Cancel',
                            ])
                            ->default('DRAFT')
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
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $get, Forms\Set $set) {
                                        $price = $get('unit_price') ?? 0;
                                        $set('subtotal', ($state ?? 0) * $price);
                                    }),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Harga Beli')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live(onBlur: true)
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
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $total = 0;
                                foreach ($state ?? [] as $item) {
                                    $total += ($item['qty'] ?? 0) * ($item['unit_price'] ?? 0);
                                }
                                $set('total_amount', $total);
                            }),
                    ]),

                Forms\Components\Section::make('Ringkasan')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('supplier.name'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'ORDERED' => 'info',
                        'PARTIAL' => 'warning',
                        'COMPLETE' => 'success',
                        'CANCEL' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('IDR'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'ORDERED' => 'Ordered',
                        'PARTIAL' => 'Partial',
                        'COMPLETE' => 'Complete',
                        'CANCEL' => 'Cancel',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('receiveAll')
                        ->label('Terima Barang')
                        ->icon('heroicon-o-truck')
                        ->color('success')
                        ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, ['DRAFT', 'ORDERED', 'PARTIAL']))
                        ->requiresConfirmation()
                        ->modalHeading('Terima Semua Barang PO')
                        ->modalDescription(fn (PurchaseOrder $record): string =>
                            'Goods Receipt akan dibuat untuk PO ' . $record->po_number .
                            ' dengan ' . $record->details->count() . ' barang. Semua qty = sisa PO. Lanjutkan?'
                        )
                        ->modalSubmitActionLabel('Ya, Terima Semua')
                        ->action(function (PurchaseOrder $record) {
                            $gr = GoodsReceipt::create([
                                'gr_number' => 'GR-' . date('Ymd') . '-' . rand(1000, 9999),
                                'date' => now(),
                                'po_id' => $record->id,
                                'supplier_id' => $record->supplier_id,
                                'warehouse_id' => Warehouse::first()?->id,
                                'status' => 'DRAFT',
                            ]);

                            foreach ($record->details as $d) {
                                $sisa = $d->remaining_qty ?? max(0, $d->qty - ($d->received_qty ?? 0));
                                if ($sisa > 0) {
                                    $gr->details()->create([
                                        'product_id' => $d->product_id,
                                        'qty' => $sisa,
                                        'buy_price' => $d->unit_price,
                                    ]);
                                }
                            }

                            Notification::make()
                                ->title('Goods Receipt berhasil dibuat')
                                ->body('GR ' . $gr->gr_number . ' telah dibuat dari PO ' . $record->po_number)
                                ->success()
                                ->send();

                            return redirect(GoodsReceiptResource::getUrl('edit', ['record' => $gr]));
                        }),

                    Tables\Actions\Action::make('printPdf')
                        ->label('Print PDF')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->action(function (PurchaseOrder $record) {
                            $company = CompanySetting::first();
                            $pdf = Pdf::loadView('pdf.po', ['po' => $record->load('details.product', 'supplier', 'creator'), 'company' => $company]);
                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->output();
                            }, 'PO-' . $record->po_number . '.pdf');
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}