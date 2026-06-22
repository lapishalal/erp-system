<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-filament::card>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Revenue Hari Ini</p>
                        <p class="text-2xl font-bold">Rp {{ number_format($this->getStats()['total_revenue_today'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <x-heroicon-o-currency-dollar class="w-8 h-8 text-success-500" />
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Order Marketplace Pending</p>
                        <p class="text-2xl font-bold">{{ $this->getStats()['pending_marketplace'] ?? 0 }}</p>
                    </div>
                    <x-heroicon-o-shopping-bag class="w-8 h-8 text-warning-500" />
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Stok Kritis</p>
                        <p class="text-2xl font-bold">{{ $this->getStats()['critical_stock'] ?? 0 }}</p>
                    </div>
                    <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-danger-500" />
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pengiriman Hari Ini</p>
                        <p class="text-2xl font-bold">{{ $this->getStats()['do_today'] ?? 0 }}</p>
                    </div>
                    <x-heroicon-o-truck class="w-8 h-8 text-primary-500" />
                </div>
            </x-filament::card>
        </div>

        {{-- Sales per Channel --}}
        <x-filament::card>
            <h3 class="text-lg font-bold mb-4">Penjualan per Channel Hari Ini</h3>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="p-4 bg-success-50 rounded-lg">
                    <p class="text-sm text-gray-600">Offline</p>
                    <p class="text-xl font-bold text-success-600">Rp {{ number_format($this->getStats()['sales_today']['offline'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-4 bg-danger-50 rounded-lg">
                    <p class="text-sm text-gray-600">TikTok Shop</p>
                    <p class="text-xl font-bold text-danger-600">Rp {{ number_format($this->getStats()['sales_today']['tiktok'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-4 bg-warning-50 rounded-lg">
                    <p class="text-sm text-gray-600">Shopee</p>
                    <p class="text-xl font-bold text-warning-600">Rp {{ number_format($this->getStats()['sales_today']['shopee'] ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        </x-filament::card>

        {{-- Recent Marketplace Orders --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-filament::card>
                <h3 class="text-lg font-bold mb-4">Order Marketplace Terbaru</h3>
                <div class="space-y-2">
                    @forelse($this->getRecentOrders() as $order)
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <div>
                                <span class="text-xs font-bold 
                                    {{ $order['source'] === 'tiktok' ? 'text-danger-600' : 'text-warning-600' }}">
                                    {{ strtoupper($order['source']) }}
                                </span>
                                <p class="text-sm">{{ $order['so_number'] }}</p>
                            </div>
                            <p class="font-bold">Rp {{ number_format($order['total_amount'], 0, ',', '.') }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">Belum ada order marketplace</p>
                    @endforelse
                </div>
            </x-filament::card>

            <x-filament::card>
                <h3 class="text-lg font-bold mb-4">Order Belum Ter-Mapping</h3>
                <div class="space-y-2">
                    @forelse($this->getUnmappedOrders() as $order)
                        <div class="flex justify-between items-center p-2 bg-danger-50 rounded">
                            <div>
                                <span class="text-xs font-bold text-danger-600">{{ strtoupper($order['platform']) }}</span>
                                <p class="text-sm">{{ $order['platform_order_sn'] }}</p>
                            </div>
                            <span class="text-xs bg-danger-100 text-danger-700 px-2 py-1 rounded">Unmapped</span>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">Semua order sudah ter-mapping</p>
                    @endforelse
                </div>
            </x-filament::card>
        </div>
    </div>
</x-filament-panels::page>