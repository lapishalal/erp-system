<?php

namespace App\Filament\Widgets;

use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\SalesOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected function getStats(): array
    {
        // Pegawai biasa (non-admin) tidak boleh lihat omset & profit
        if (!auth()->check() && auth()->user()->hasRole('Admin') && !auth()->check() && auth()->user()->hasPermissionTo('view_reports')) {
            return [
                Stat::make('Omset Hari Ini', 'Rp ' . number_format($omsetToday, 0, ',', '.'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success'),
                Stat::make('Profit Hari Ini', 'Rp ' . number_format($profitToday, 0, ',', '.'))
                    ->icon('heroicon-o-chart-pie')
                    ->color('info'),
                Stat::make('Outstanding Order', 'Rp ' . number_format($outstanding, 0, ',', '.'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('warning'),
                Stat::make('Cash Position', 'Rp ' . number_format($cashPosition, 0, ',', '.'))
                    ->icon('heroicon-o-wallet')
                    ->color($cashPosition >= 0 ? 'success' : 'danger'),
            ];
        }

        $today = now()->startOfDay();
        $endToday = now()->endOfDay();

        $omsetToday = SalesOrder::whereBetween('date', [$today, $endToday])
            ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE'])
            ->sum('total_amount');

        $profitToday = SalesOrder::whereBetween('date', [$today, $endToday])
            ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE'])
            ->sum('profit');

        $outstanding = SalesOrder::whereIn('status', ['OPEN', 'PARTIAL'])
            ->sum('total_amount');

        $cashIn = CashIn::sum('amount');
        $cashOut = CashOut::sum('amount');
        $cashPosition = $cashIn - $cashOut;

        return [
            Stat::make('Selamat Datang', auth()->user()->name)
                    ->icon('heroicon-o-user')
                    ->color('info'),
            Stat::make('Status', 'Aktif')
                    ->icon('heroicon-o-check-circle')
                    ->color('success'),
        ];
    }
}