<x-filament-widgets::widget class="fi-wi-stats-overview">
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-500"/>
                Stok Menipis ({{ $this->getTotalLowStock() }} barang)
            </h3>
            <a href="{{ url('/admin/product-stocks') }}" class="text-sm text-primary-600 hover:underline">
                Lihat Semua →
            </a>
        </div>

        @if(count($this->getLowStockItems()) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Nama Barang</th>
                            <th class="px-4 py-3">Gudang</th>
                            <th class="px-4 py-3 text-right">Stok Fisik</th>
                            <th class="px-4 py-3 text-right">Available</th>
                            <th class="px-4 py-3 text-right">Min Stok</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getLowStockItems() as $item)
                            <tr class="border-b dark:border-gray-700 {{ $item['is_critical'] ? 'bg-danger-50 dark:bg-danger-900/20' : 'bg-warning-50 dark:bg-warning-900/20' }}">
                                <td class="px-4 py-3 font-medium">{{ $item['product_code'] }}</td>
                                <td class="px-4 py-3">{{ $item['product_name'] }}</td>
                                <td class="px-4 py-3">{{ $item['warehouse'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item['physical_stock'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-bold {{ $item['available_stock'] <= 0 ? 'text-danger-600' : 'text-warning-600' }}">
                                    {{ number_format($item['available_stock'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($item['min_stock'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    @if($item['is_critical'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800">
                                            HABIS
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800">
                                            MENIPIS
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                <x-heroicon-o-check-circle class="w-12 h-12 mx-auto mb-2 text-success-500"/>
                <p>Semua stok dalam kondisi aman.</p>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>