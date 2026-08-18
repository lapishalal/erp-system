<div class="space-y-4">
    @if($details->isEmpty())
        <p class="text-gray-500 text-center py-4">Tidak ada produk untuk periode yang dipilih.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 uppercase">
                    <tr>
                        <th class="px-4 py-2 rounded-tl-lg">#</th>
                        <th class="px-4 py-2">Kode</th>
                        <th class="px-4 py-2">Produk</th>
                        <th class="px-4 py-2 text-center">Jumlah (pcs)</th>
                        <th class="px-4 py-2 text-right rounded-tr-lg">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($details as $index => $detail)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-2 text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-2"><code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ $detail->product?->code ?? '-' }}</code></td>
                            <td class="px-4 py-2 font-medium">{{ $detail->product?->name ?? 'Produk tidak ditemukan' }}</td>
                            <td class="px-4 py-2 text-center font-bold">{{ number_format($detail->total_qty, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">Rp {{ number_format($detail->total_subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800 font-semibold">
                    <tr>
                        <td class="px-4 py-2 rounded-bl-lg" colspan="3">Total</td>
                        <td class="px-4 py-2 text-center">{{ number_format($details->sum('total_qty'), 0, ',', '.') }} pcs</td>
                        <td class="px-4 py-2 text-right rounded-br-lg">Rp {{ number_format($details->sum('total_subtotal'), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
