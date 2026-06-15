<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopCustomersTable extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Top Customer';

    public static function canView(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('view_reports');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->select('customers.*')
                    ->selectRaw('COALESCE((SELECT SUM(total_amount) FROM sales_orders WHERE sales_orders.customer_id = customers.id AND sales_orders.status IN (\'OPEN\',\'PARTIAL\',\'COMPLETE\')), 0) as total_omset')
                    ->selectRaw('COALESCE((SELECT SUM(profit) FROM sales_orders WHERE sales_orders.customer_id = customers.id AND sales_orders.status IN (\'OPEN\',\'PARTIAL\',\'COMPLETE\')), 0) as total_profit')
                    ->orderByDesc('total_omset')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('total_omset')
                    ->label('Omset')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('total_profit')
                    ->label('Profit')
                    ->money('IDR'),
            ])
            ->paginated(false);
    }
}