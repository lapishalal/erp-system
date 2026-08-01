<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class TaxReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Pajak';
    protected static ?string $title = 'Laporan Pajak';
    protected static string $view = 'filament.pages.tax-report';
    protected static ?string $slug = 'laporan-pajak';
    protected static ?int $sort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && (
            auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('view_reports')
            || auth()->user()->hasPermissionTo('view_financial_report')
        );
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->format('Y-m-d'),
            'to_date' => now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from_date')
                    ->label('Dari Tanggal')
                    ->required()
                    ->default(now()->startOfMonth()),
                DatePicker::make('to_date')
                    ->label('Sampai Tanggal')
                    ->required()
                    ->default(now()->endOfMonth()),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function getReportData(): array
    {
        $from = $this->data['from_date'] ?? null;
        $to = $this->data['to_date'] ?? null;

        if (!$from || !$to) {
            return ['rows' => [], 'summary' => [], 'total_debit' => 0, 'total_credit' => 0];
        }

        $rows = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->whereBetween('je.date', [$from, $to])
            ->where('je.is_posted', true)
            ->where(function ($q) {
                $q->where('a.code', 'like', '2-200%')
                    ->orWhere('a.code', 'like', '5-20007')
                    ->orWhere('a.name', 'like', '%PPN%')
                    ->orWhere('a.name', 'like', '%Pajak%')
                    ->orWhere('a.name', 'like', '%Tax%');
            })
            ->select(
                'je.date',
                'je.id as journal_id',
                'je.description',
                'a.code as account_code',
                'a.name as account_name',
                'jed.debit',
                'jed.credit'
            )
            ->orderBy('je.date')
            ->orderBy('je.id')
            ->get();

        $summary = $rows->groupBy('account_code')
            ->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'code' => $first->account_code,
                    'name' => $first->account_name,
                    'debit' => $group->sum('debit'),
                    'credit' => $group->sum('credit'),
                    'saldo' => $group->sum('credit') - $group->sum('debit'),
                ];
            })
            ->values();

        return [
            'rows' => $rows,
            'summary' => $summary,
            'total_debit' => $rows->sum('debit'),
            'total_credit' => $rows->sum('credit'),
        ];
    }
}
