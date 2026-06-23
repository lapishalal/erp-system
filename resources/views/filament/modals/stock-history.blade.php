<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase tracking-wider">Produk</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ $product->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase tracking-wider">Kode</span>
                <span class="font-semibold text-gray-900 dark:text-white font-mono">{{ $product->code ?? '-' }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase tracking-wider">Gudang</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ $warehouse->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase tracking-wider">Total Transaksi</span>
                <span class="font-bold text-primary-600 dark:text-primary-400 text-lg">
                    {{ count($history) }} transaksi
                </span>
            </div>
        </div>
    </div>

    @if($history->isEmpty())
        <div class="text-center py-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-50 dark:bg-gray-800 mb-4">
                <x-heroicon-o-inbox class="w-8 h-8 text-gray-400"/>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Tidak Ada History</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Belum ada transaksi stok untuk produk ini.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Tanggal</th>
                        <th class="px-4 py-3 text-left font-medium">Jenis</th>
                        <th class="px-4 py-3 text-left font-medium">Referensi</th>
                        <th class="px-4 py-3 text-left font-medium">Supplier / Customer</th>
                        <th class="px-4 py-3 text-right font-medium">Qty</th>
                        <th class="px-4 py-3 text-right font-medium">Harga</th>
                        <th class="px-4 py-3 text-right font-medium">Stok Akhir</th>
                        <th class="px-4 py-3 text-left font-medium">Keterangan</th>
                        <th class="px-4 py-3 text-left font-medium">Oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                    @foreach($history as $item)
                        @php
                            $isIn = $item->type === 'IN';
                            $isOut = $item->type === 'OUT';
                            $typeColor = match($item->type) {
                                'IN' => 'bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-300',
                                'OUT' => 'bg-danger-100 text-danger-800 dark:bg-danger-900/30 dark:text-danger-300',
                                'ADJUSTMENT' => 'bg-warning-100 text-warning-800 dark:bg-warning-900/30 dark:text-warning-300',
                                'OPNAME' => 'bg-info-100 text-info-800 dark:bg-info-900/30 dark:text-info-300',
                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                            };
                            $typeLabel = match($item->type) {
                                'IN' => 'Masuk',
                                'OUT' => 'Keluar',
                                'ADJUSTMENT' => 'Penyesuaian',
                                'OPNAME' => 'Stock Opname',
                                default => $item->type,
                            };
                            $refType = class_basename($item->reference_type ?? '');
                            $refLabel = match($refType) {
                                'GoodsReceiptDetail' => 'GR',
                                'DeliveryOrderDetail' => 'DO',
                                'StockOpname' => 'Opname',
                                default => $refType,
                            };

                            // Extract supplier/customer from notes
                            $notes = $item->notes ?? '';
                            $party = '-';
                            if (str_contains($notes, 'Supplier:')) {
                                $party = trim(explode('Supplier:', $notes)[1] ?? '-');
                            } elseif (str_contains($notes, 'Customer:')) {
                                $party = trim(explode('Customer:', $notes)[1] ?? '-');
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                {{ $item->created_at?->format('d M Y H:i') ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColor }}">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">
                                    {{ $refLabel }} #{{ $item->reference_id }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                {{ $party }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ $isIn ? 'text-success-600' : ($isOut ? 'text-danger-600' : 'text-gray-600') }}">
                                {{ $isIn ? '+' : '' }}{{ number_format($item->qty, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">
                                @if($item->price && $item->price > 0)
                                    Rp {{ number_format($item->price, 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-primary-600 dark:text-primary-400">
                                {{ number_format($item->remaining_stock, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300 max-w-xs truncate" title="{{ $notes }}">
                                {{ $notes }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">
                                {{ $item->creator?->name ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
