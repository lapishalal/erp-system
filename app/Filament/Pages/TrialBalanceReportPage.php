<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class TrialBalanceReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Neraca Saldo';
    protected static ?string $title = 'Neraca Saldo';
    protected static string $view = 'filament.pages.trial-balance-report';
    protected static ?string $slug = 'neraca-saldo';
    protected static ?int $sort = 23;

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
            return ['rows' => [], 'total_debit' => 0, 'total_credit' => 0];
        }

        $rows = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->whereBetween('je.date', [$from, $to])
            ->where('je.is_posted', true)
            ->select(
                'a.id',
                'a.code',
                'a.name',
                'a.type',
                'a.normal_balance'
            )
            ->selectRaw('SUM(jed.debit) as total_debit')
            ->selectRaw('SUM(jed.credit) as total_credit')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.normal_balance')
            ->orderBy('a.code')
            ->get();

        $mapped = $rows->map(function ($r) {
            $debit = (float) $r->total_debit;
            $credit = (float) $r->total_credit;
            $saldo = $r->normal_balance === 'CREDIT' ? $credit - $debit : $debit - $credit;

            return (object) [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'type' => $r->type,
                'normal_balance' => $r->normal_balance,
                'debit' => $debit,
                'credit' => $credit,
                'saldo' => $saldo,
            ];
        });

        return [
            'rows' => $mapped,
            'total_debit' => $mapped->sum('debit'),
            'total_credit' => $mapped->sum('credit'),
        ];
    }
}
