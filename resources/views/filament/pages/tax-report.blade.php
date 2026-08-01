<x-filament-panels::page>
    <x-filament-panels::form wire:submit="getReportData">
        {{ $this->form }}

        <x-filament::button type="submit" wire:loading.attr="disabled">
            Tampilkan
        </x-filament::button>
    </x-filament-panels::form>

    @php
        $report = $this->getReportData();
        $from = $this->data['from_date'] ?? null;
        $to = $this->data['to_date'] ?? null;
    @endphp

    @if($from && $to)
        <div class="mt-6 space-y-4">
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Periode</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">
                        {{ \Carbon\Carbon::parse($from)->format('d M Y') }} s.d. {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
                    </p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Debit Pajak</p>
                    <p class="text-lg font-bold text-red-700 mt-1">Rp {{ number_format($report['total_debit'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Kredit Pajak</p>
                    <p class="text-lg font-bold text-green-700 mt-1">Rp {{ number_format($report['total_credit'], 0, ',', '.') }}</p>
                </div>
            </div>

            @if($report['summary']->isNotEmpty())
                <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                    <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 dark:bg-gray-700">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Ringkasan per Akun Pajak</p>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 border-b border-gray-200">
                                <th class="px-4 py-2 font-medium">Kode</th>
                                <th class="px-4 py-2 font-medium">Akun</th>
                                <th class="px-4 py-2 font-medium text-right">Debit</th>
                                <th class="px-4 py-2 font-medium text-right">Kredit</th>
                                <th class="px-4 py-2 font-medium text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($report['summary'] as $s)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $s->code }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $s->name }}</td>
                                    <td class="px-4 py-2 text-right text-red-600">Rp {{ number_format($s->debit, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-green-600">Rp {{ number_format($s->credit, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">Rp {{ number_format($s->saldo, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 dark:bg-gray-700">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Detail Transaksi Pajak</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-center font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-left font-semibold">No. Jurnal</th>
                            <th class="px-4 py-3 text-left font-semibold">Keterangan</th>
                            <th class="px-4 py-3 text-left font-semibold">Akun</th>
                            <th class="px-4 py-3 text-right font-semibold">Debit</th>
                            <th class="px-4 py-3 text-right font-semibold">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($report['rows'] as $r)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 text-center">{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-mono text-xs">JU-{{ str_pad($r->journal_id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-4 py-3">{{ $r->description }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs">{{ $r->account_code }}</span> - {{ $r->account_name }}
                                </td>
                                <td class="px-4 py-3 text-right text-red-600">
                                    {{ $r->debit > 0 ? 'Rp ' . number_format($r->debit, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right text-green-600">
                                    {{ $r->credit > 0 ? 'Rp ' . number_format($r->credit, 0, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="6">
                                    Tidak ada transaksi pajak pada periode ini
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
