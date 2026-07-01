<div class="space-y-4">
    @if($orders->isEmpty())
        <p class="text-gray-500 text-center py-4">Tidak ada order untuk periode yang dipilih.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 uppercase">
                    <tr>
                        <th class="px-4 py-2 rounded-tl-lg">No. SO</th>
                        <th class="px-4 py-2">Tanggal</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Omset</th>
                        <th class="px-4 py-2 text-right">HPP</th>
                        <th class="px-4 py-2 text-right rounded-tr-lg">Profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($orders as $order)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-2 font-medium">{{ $order->so_number }}</td>
                            <td class="px-4 py-2">{{ $order->date?->format('d M Y') }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $statusClass = match($order->status) {
                                        'COMPLETE' => 'bg-green-100 text-green-800',
                                        'OPEN' => 'bg-blue-100 text-blue-800',
                                        'PARTIAL' => 'bg-amber-100 text-amber-800',
                                        default => 'bg-gray-100 text-gray-800',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                    {{ $order->status }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">Rp {{ number_format($order->total_cost, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right font-medium {{ $order->profit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                Rp {{ number_format($order->profit, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800 font-semibold">
                    <tr>
                        <td class="px-4 py-2 rounded-bl-lg" colspan="3">Total</td>
                        <td class="px-4 py-2 text-right">Rp {{ number_format($orders->sum('total_amount'), 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">Rp {{ number_format($orders->sum('total_cost'), 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right rounded-br-lg {{ $orders->sum('profit') >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            Rp {{ number_format($orders->sum('profit'), 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
