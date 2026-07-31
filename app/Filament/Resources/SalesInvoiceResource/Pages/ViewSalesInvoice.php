<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\Account;
use App\Models\CashIn;
use App\Models\SalesInvoice;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Faktur')
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Nomor Faktur'),
                        TextEntry::make('date')
                            ->date('d M Y')
                            ->label('Tanggal'),
                        TextEntry::make('due_date')
                            ->date('d M Y')
                            ->label('Jatuh Tempo'),
                        TextEntry::make('customer.name')
                            ->label('Customer'),
                        TextEntry::make('salesOrder.so_number')
                            ->label('SO')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'UNPAID' => 'warning',
                                'PARTIAL' => 'info',
                                'PAID' => 'success',
                                'OVERDUE' => 'danger',
                                'CANCEL' => 'gray',
                            }),
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Pembayaran & Riwayat Cicilan')
                    ->schema([
                        \App\Filament\Components\PaymentHistory::make('payment_history')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('print')
                ->label('Print PDF')
                ->icon('heroicon-o-printer')
                ->url(fn () => url('/invoice/' . $this->record->id . '/print'))
                ->openUrlInNewTab(),
            Actions\Action::make('receivePayment')
                ->label('Terima Pembayaran')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('account_id')
                        ->label('Akun Kas/Bank')
                        ->options(Account::where('type', 'ASSET')->whereIn('code', ['1-10001', '1-10002'])->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\DatePicker::make('date')
                        ->label('Tanggal')
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('amount')
                        ->label('Jumlah')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(fn (): float => (float) $this->record->total - (float) $this->record->paid_amount)
                        ->required(),
                    Forms\Components\Textarea::make('description')
                        ->label('Keterangan')
                        ->default(fn (): string => 'Pembayaran faktur ' . $this->record->invoice_number),
                ])
                ->action(function (array $data): void {
                    $invoice = $this->record;
                    $remaining = (float) $invoice->total - (float) $invoice->paid_amount;
                    $amount = (float) $data['amount'];

                    if ($amount <= 0) {
                        Notification::make()
                            ->title('Jumlah harus lebih dari 0')
                            ->danger()
                            ->send();
                        return;
                    }

                    if ($amount > $remaining) {
                        Notification::make()
                            ->title('Jumlah melebihi sisa tagihan (Rp ' . number_format($remaining, 0, ',', '.') . ')')
                            ->danger()
                            ->send();
                        return;
                    }

                    CashIn::create([
                        'account_id' => $data['account_id'],
                        'date' => $data['date'],
                        'type' => 'CUSTOMER_PAYMENT',
                        'reference_type' => SalesInvoice::class,
                        'reference_id' => $invoice->id,
                        'customer_id' => $invoice->customer_id,
                        'amount' => $amount,
                        'description' => $data['description'] ?? ('Pembayaran faktur ' . $invoice->invoice_number),
                        'created_by' => auth()->id(),
                    ]);

                    $this->record->refresh();

                    Notification::make()
                        ->title($remaining - $amount <= 0 ? 'Pembayaran diterima, faktur lunas' : 'Pembayaran diterima (cicilan)')
                        ->success()
                        ->send();
                }),
        ];
    }
}
