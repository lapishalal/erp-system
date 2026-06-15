<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Audit Log';
    protected static ?string $modelLabel = 'Audit Log';
    protected static ?string $pluralModelLabel = 'Audit Log';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('view_audit_log');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->searchable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Dibuat',
                        'updated' => 'Diubah',
                        'deleted' => 'Dihapus',
                        'restored' => 'Dikembalikan',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Model')
                    ->formatStateUsing(function ($state) {
                        return class_basename($state);
                    }),

                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('ID Record')
                    ->numeric(),

                Tables\Columns\TextColumn::make('changed_fields')
                    ->label('Field Berubah')
                    ->default(function (AuditLog $record) {
                        if ($record->action === 'updated' && is_array($record->new_values)) {
                            return implode(', ', array_keys($record->new_values));
                        }
                        return '-';
                    })
                    ->limit(30),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Dari'),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['sampai'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),

                Tables\Filters\SelectFilter::make('action')
                    ->label('Aksi')
                    ->options([
                        'created' => 'Dibuat',
                        'updated' => 'Diubah',
                        'deleted' => 'Dihapus',
                        'restored' => 'Dikembalikan',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('auditable_type')
                    ->label('Model')
                    ->options([
                        'App\Models\Product' => 'Barang',
                        'App\Models\SalesOrder' => 'Sales Order',
                        'App\Models\Customer' => 'Customer',
                        'App\Models\Supplier' => 'Supplier',
                        'App\Models\SalesInvoice' => 'Faktur',
                        'App\Models\CashIn' => 'Kas Masuk',
                        'App\Models\CashOut' => 'Kas Keluar',
                        'App\Models\User' => 'User',
                        'App\Models\PosTransaction' => 'POS',
                        'App\Models\StockOpname' => 'Stock Opname',
                        'App\Models\PurchaseOrder' => 'Purchase Order',
                        'App\Models\GoodsReceipt' => 'Stok Masuk',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->paginated([25, 50, 100])
            ->poll(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}