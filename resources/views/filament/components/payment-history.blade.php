{{-- resources/views/filament/components/payment-history.blade.php --}}
@php
    $state = $getState();
    $total = $state['total'] ?? 0;
    $paid = $state['paid'] ?? 0;
    $remaining = $state['remaining'] ?? 0;
    $payments = $state['payments'] ?? collect();
@endphp

<div class="w-full space-y-4">
    {{-- Ringkasan --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500">Total Faktur</p>
            <p class="text-lg font-bold text-gray-800 mt-1">Rp {{ number_format($total, 0, ',', '.') }}</p>
        </div>
        <div class="bg-green-50 rounded-lg border border-green-200 p-4">
            <p class="text-xs font-medium text-green-600">Sudah Dibayar</p>
            <p class="text-lg font-bold text-green-700 mt-1">Rp {{ number_format($paid, 0, ',', '.') }}</p>
        </div>
        <div class="{{ $remaining > 0 ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200' }} rounded-lg border p-4">
            <p class="text-xs font-medium {{ $remaining > 0 ? 'text-amber-600' : 'text-blue-600' }}">Sisa Tagihan</p>
            <p class="text-lg font-bold {{ $remaining > 0 ? 'text-amber-700' : 'text-blue-700' }} mt-1">Rp {{ number_format($remaining, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Riwayat Pembayaran --}}
    <div class="border rounded-lg overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2">
            <p class="text-sm font-semibold text-gray-700">Riwayat Pembayaran ({{ $payments->count() }})</p>
        </div>

        @if($payments->isEmpty())
            <div class="px-4 py-6 text-center">
                <p class="text-sm text-gray-400">Belum ada pembayaran tercatat</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500 border-b border-gray-200 bg-white">
                            <th class="px-4 py-2 font-medium">No</th>
                            <th class="px-4 py-2 font-medium">Tanggal</th>
                            <th class="px-4 py-2 font-medium">Jumlah</th>
                            <th class="px-4 py-2 font-medium">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach($payments as $index => $payment)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ $index + 1 }}</td>
                                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($payment->date)->format('d M Y') }}</td>
                                <td class="px-4 py-2 font-semibold text-green-600">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $payment->description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
