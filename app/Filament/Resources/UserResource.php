<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'User & Hak Akses';
    protected static ?string $modelLabel = 'User';
    protected static ?string $pluralModelLabel = 'User';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('manage_users');
    }
    
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', auth()->user()->tenant_id);
    }
	
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi User')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->revealable()
                            ->helperText(fn (string $context): string => $context === 'edit' ? 'Kosongkan jika tidak ingin mengubah password' : ''),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telepon / WA')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\FileUpload::make('avatar')
                            ->label('Foto Profil')
                            ->image()
                            ->directory('avatars')
                            ->maxSize(2048)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Hak Akses — Centang Fitur yang Diizinkan')
                    ->description('Admin bebas menentukan fitur apa saja yang bisa diakses oleh user ini')
                    ->visible(fn (?User $record): bool => auth()->check() && auth()->user()->hasRole('Admin') || auth()->id() !== $record?->id)
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->label(false)
                            ->relationship('permissions', 'name')
                            ->options(function () {
                                return \Spatie\Permission\Models\Permission::where('tenant_id', auth()->user()->tenant_id)->get()
                                    ->mapWithKeys(function ($permission) {
                                        $label = match ($permission->name) {
                                            'view_dashboard' => '📊 Dashboard',
                                            'view_sales_report' => '📈 Laporan Penjualan',
                                            'view_stock_report' => '📦 Laporan Stok',
                                            'view_financial_report' => '💰 Laporan Keuangan',
                                            'view_profit_loss' => '📉 Profit & Loss',
                                            'view_balance_sheet' => '📋 Neraca',
                                            'view_cash_flow' => '💸 Cash Flow',
                                            'manage_master_data' => '🏷️ Master Data (Brand/Kategori/Gudang)',
                                            'manage_products' => '📦 Barang',
                                            'manage_customers' => '👥 Customer',
                                            'manage_suppliers' => '🚚 Supplier',
                                            'manage_sales_orders' => '📝 Sales Order',
                                            'manage_delivery_orders' => '🚚 Surat Jalan',
                                            'manage_sales_invoices' => '🧾 Faktur Penjualan',
                                            'manage_purchase_orders' => '🛒 Purchase Order',
                                            'manage_goods_receipts' => '📥 Stok Masuk / GR',
                                            'manage_purchase_returns' => '↩️ Retur Pembelian',
                                            'manage_inventory' => '🏭 Inventory / Cek Stok',
                                            'manage_stock_opname' => '🔍 Stock Opname',
                                            'manage_cash_in' => '💵 Kas Masuk',
                                            'manage_cash_out' => '💸 Pengeluaran / Kas Keluar',
                                            'manage_expenses' => '🏷️ Kategori Expense',
                                            'manage_accounting' => '📚 Jurnal & Buku Besar',
                                            'manage_users' => '👤 User & Hak Akses',
                                            'manage_settings' => '⚙️ Pengaturan',
											'manage_pos' => '🛒 POS / Kasir',
											'manage_payroll' => '💼 Payroll / Gaji Karyawan',
                                            default => $permission->name,
                                        };
                                        return [$permission->id => $label];
                                    });
                            })
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(2)
                            ->gridDirection('row'),
                    ]),
                    
                Forms\Components\Section::make('Telegram')
                    ->description('Hubungkan akun Telegram untuk notifikasi dan input transaksi via bot')
                    ->schema([
                        Forms\Components\Placeholder::make('telegram_status')
                            ->label('Status')
                            ->content(function ($record) {
                                if ($record->telegram_chat_id) {
                                    return new \Illuminate\Support\HtmlString('✅ <span class="text-success-600 font-bold">Terhubung</span>');
                                }
                                return new \Illuminate\Support\HtmlString('❌ <span class="text-danger-600">Belum terhubung</span>');
                            }),

        Forms\Components\Placeholder::make('telegram_chat_id')
            ->label('Chat ID')
            ->visible(fn ($record) => !empty($record->telegram_chat_id))
            ->content(fn ($record) => $record->telegram_chat_id),

        Forms\Components\Actions::make([
            Forms\Components\Actions\Action::make('generate_link_code')
                ->label(fn ($record) => $record->telegram_chat_id ? 'Ganti Koneksi' : 'Hubungkan Telegram')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->form([
                    Forms\Components\Placeholder::make('instruction')
                        ->label('Langkah')
                        ->content(new \Illuminate\Support\HtmlString('
                            <ol class="list-decimal pl-4 space-y-1">
                                <li>Klik tombol <b>Buka Bot</b> di bawah</li>
                                <li>Kirim pesan: <code>/link {kode}</code></li>
                                <li>Kode berlaku 30 menit</li>
                            </ol>
                        ')),
                    Forms\Components\Placeholder::make('generated_code')
                        ->label('Kode Anda')
                        ->content(function ($record) {
                            // Hapus kode lama yang belum dipakai
                            TelegramLinkCode::where('user_id', $record->id)
                                ->where('is_used', false)
                                ->delete();

                            $code = strtoupper(substr(md5(uniqid()), 0, 6));
                            
                            TelegramLinkCode::create([
                                'code' => $code,
                                'user_id' => $record->id,
                                'tenant_id' => null, // atau auth()->user()->tenant_id
                                'expires_at' => now()->addMinutes(30),
                            ]);

                            return new \Illuminate\Support\HtmlString('
                                <div class="bg-gray-100 dark:bg-gray-800 p-3 rounded-lg text-center font-mono text-lg font-bold tracking-widest">
                                    ' . $code . '
                                </div>
                            ');
                        }),
                ])
                ->modalHeading('Hubungkan Telegram')
                ->modalSubmitActionLabel('Tutup')
                ->action(function () {}),

            Forms\Components\Actions\Action::make('open_bot')
                ->label('Buka Bot')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->url('https://t.me/Erpmantap_Bot')
                ->openUrlInNewTab(),
        ])
        ->columnSpanFull(),
    ]),
    
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&background=0D8ABC&color=fff'),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('phone')
                    ->placeholder('-'),
                
                Tables\Columns\TextColumn::make('roles')
                    ->label('Role')
                    ->formatStateUsing(fn (User $record): string => $record->roles->pluck('name')->join(', '))
                    ->placeholder('-'),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => auth()->check() && $record->id !== auth()->id()),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}