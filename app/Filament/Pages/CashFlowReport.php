<?php

namespace App\Filament\Pages;

use App\Models\CashIn;
use App\Models\CashOut;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Carbon\Carbon;

class CashFlowReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Cash Flow';
    protected static ?string $slug = 'cash-flow-report';
    protected static string $view = 'filament.pages.cash-flow-report';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('view_cash_flow');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'year' => now()->year,
            'month' => now()->month,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('year')
                    ->label('Tahun')
                    ->options(array_combine(range(2024, 2030), range(2024, 2030)))
                    ->default(now()->year)
                    ->live(),
                Select::make('month')
                    ->label('Bulan')
                    ->options([
                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                    ])
                    ->default(now()->month)
                    ->live(),
            ])
            ->statePath('data');
    }

    /**
     * PERBAIKAN AKUNTANSI (PRIORITAS TINGGI):
     * Menghitung Saldo Awal Kas & Bank secara kumulatif dari seluruh transaksi CashIn & CashOut
     * sebelum tanggal 1 bulan berjalan yang sedang difilter.
     */
    public function getSaldoAwal(): float
    {
        $year = $this->data['year'] ?? now()->year;
        $month = $this->data['month'] ?? now()->month;
        
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        
        $totalInBefore = (float) CashIn::whereDate('date', '<', $startOfMonth)->sum('amount');
        $totalOutBefore = (float) CashOut::whereDate('date', '<', $startOfMonth)->sum('amount');
        
        return $totalInBefore - $totalOutBefore;
    }

    public function getCashInQuery()
    {
        return CashIn::with('customer')
            ->whereYear('date', $this->data['year'] ?? now()->year)
            ->whereMonth('date', $this->data['month'] ?? now()->month)
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getCashOutQuery()
    {
        return CashOut::with('category')
            ->whereYear('date', $this->data['year'] ?? now()->year)
            ->whereMonth('date', $this->data['month'] ?? now()->month)
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getTotalCashIn(): float
    {
        return (float) CashIn::whereYear('date', $this->data['year'] ?? now()->year)
            ->whereMonth('date', $this->data['month'] ?? now()->month)
            ->sum('amount');
    }

    public function getTotalCashOut(): float
    {
        return (float) CashOut::whereYear('date', $this->data['year'] ?? now()->year)
            ->whereMonth('date', $this->data['month'] ?? now()->month)
            ->sum('amount');
    }

    public function getNetCashFlow(): float
    {
        return $this->getTotalCashIn() - $this->getTotalCashOut();
    }

    public function getSaldoAkhir(): float
    {
        return $this->getSaldoAwal() + $this->getNetCashFlow();
    }
}
