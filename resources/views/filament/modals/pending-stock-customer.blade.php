<div x-data="{ selectedCustomer: 'all' }" class="space-y-4">

    {{-- Header Ringkasan --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase tracking-wider">Produk</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ $product->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase tracking-wider">Gudang</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ $warehouse->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase tracking-wider">Total Pending</span>
                <span class="font-bold text-warning-600 dark:text-warning-400 text-lg">
                    {{ number_format($totalPending, 0, ',', '.') }} unit
                </span>
            </div>
        </div>
    </div>

    {{-- Filter by Customer --}}
    @if(count($customers) > 1)
    <div class="flex items-center gap-2 bg-white dark:bg-gray-900 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
        <x-heroicon-o-funnel class="w-5 h-5 text-gray-400"/>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Filter Customer:</label>
        <select x-model="selectedCustomer" class="text-sm border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:text-white">
            <option value="all">-- Semua Customer --</option>
            @foreach($customers as $c)
                <option value="{{ $c['customer_id'] }}">{{ $c['customer_name'] }} ({{ number_format($c['total_pending'], 0, ',', '.') }} unit)</option>
            @endforeach
        </select>
    </div>
    @endif

    {{-- Empty State --}}
    @if(empty($customers))
        <div class="text-center py-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-success-50 dark:bg-success-900/20 mb-4">
                <x-heroicon-o-check-circle class="w-8 h-8 text-success-500"/>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Tidak Ada Pending Stok</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Semua sales order untuk produk ini sudah terkirim.</p>
        </div>
    @else
        {{-- List per Customer --}}
        <div class="space-y-3">
            @foreach($customers as $customerData)
                <div x-show="selectedCustomer === 'all' || selectedCustomer == '{{ $customerData['customer_id'] }}'"
                     x-transition
                     class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-900">

                    {{-- Customer Header --}}
                    <div class="bg-gray-100 dark:bg-gray-800 px-4 py-3 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                                <x-heroicon-o-user class="w-4 h-4 text-primary-600 dark:text-primary-400"/>
                            </div>
                            <div>
                                <div class="font-semibold text-sm text-gray-900 dark:text-white">
                                    {{ $customerData['customer_name'] }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ count($customerData['orders'] ?? []) }} Sales Order
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Total Pending:</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-warning-100 text-warning-800 dark:bg-warning-900/30 dark:text-warning-300">
                                {{ number_format($customerData['total_pending'], 0, ',', '.') }} unit
                            </span>
                        </div>
                    </div>

                    {{-- Detail Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                    <th class="px-4 py-2 font-medium">No. SO</th>
                                    <th class="px-4 py-2 font-medium">Tanggal Order</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                    <th class="px-4 py-2 font-medium text-right">Qty Order</th>
                                    <th class="px-4 py-2 font-medium text-right">Qty Terkirim</th>
                                    <th class="px-4 py-2 font-medium text-right">Qty Pending</th>
                                    <th class="px-4 py-2 font-medium text-right">Harga Satuan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($customerData['orders'] ?? [] as $order)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex items-center gap-1 font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">
                                                <x-heroicon-o-document-text class="w-3.5 h-3.5"/>
                                                {{ $order['so_number'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">
                                            {{ $order['so_date'] ?? '-' }}
                                        </td>
                                        <td class="px-4 py-2.5">
                                            @php
                                                $statusColor = match($order['so_status'] ?? '') {
                                                    'OPEN' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                    'PARTIAL' => 'bg-warning-100 text-warning-800 dark:bg-warning-900/30 dark:text-warning-300',
                                                    'COMPLETE' => 'bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-300',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusColor }}">
                                                {{ $order['so_status'] ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-300">
                                            {{ number_format($order['qty'] ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-success-600 dark:text-success-400">
                                            {{ number_format($order['delivered_qty'] ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <span class="font-bold text-warning-600 dark:text-warning-400">
                                                {{ number_format($order['remaining_qty'] ?? 0, 0, ',', '.') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-300">
                                            Rp {{ number_format($order['unit_price'] ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                    <td colspan="5" class="px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">
                                        Total Pending {{ $customerData['customer_name'] }}:
                                    </td>
                                    <td class="px-4 py-2 text-right font-bold text-warning-600 dark:text-warning-400">
                                        {{ number_format($customerData['total_pending'] ?? 0, 0, ',', '.') }} unit
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Grand Total Footer --}}
        <div class="bg-warning-50 dark:bg-warning-900/10 border border-warning-200 dark:border-warning-800 rounded-lg p-4 flex justify-between items-center">
            <span class="font-semibold text-warning-800 dark:text-warning-300">
                <x-heroicon-o-calculator class="w-5 h-5 inline mr-1 -mt-0.5"/>
                Grand Total Pending ke Semua Customer
            </span>
            <span class="text-xl font-bold text-warning-700 dark:text-warning-300">
                {{ number_format($totalPending, 0, ',', '.') }} unit
            </span>
        </div>
    @endif
</div>
