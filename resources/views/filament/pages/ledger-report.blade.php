<x-filament-panels::page>
    <x-filament-panels::form wire:submit="getReportData">
        {{ $this->form }}
        
        <x-filament::button type="submit" wire:loading.attr="disabled">
            Tampilkan
        </x-filament::button>
    </x-filament-panels::form>

    @php
        $report = $this->getReportData();
    @endphp

    @if($report['account'])
        <div class="mt-6 space-y-4">
            {{-- Info Akun --}}
            <div class="p-4 bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Kode Akun:</span>
                        <span class="font-semibold ml-1">{{ $report['account']->code }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Nama Akun:</span>
                        <span class="font-semibold ml-1">{{ $report['account']->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Tipe:</span>
                        <span class="font-semibold ml-1">{{ $report['account']->type }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Normal Balance:</span>
                        <span class="font-semibold ml-1">{{ $report['account']->normal_balance }}</span>
                    </div>
                </div>
            </div>

            {{-- Tabel Buku Besar --}}
            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-left font-semibold">No. Jurnal</th>
                            <th class="px-4 py-3 text-left font-semibold">Keterangan</th>
                            <th class="px-4 py-3 text-right font-semibold">Debit</th>
                            <th class="px-4 py-3 text-right font-semibold">Kredit</th>
                            <th class="px-4 py-3 text-right font-semibold">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        {{-- Saldo Awal --}}
                        <tr class="bg-yellow-50 dark:bg-yellow-900/20">
                            <td class="px-4 py-3 text-gray-500" colspan="5">Saldo Awal</td>
                            <td class="px-4 py-3 text-right font-semibold">
                                Rp {{ number_format($report['saldo_awal'], 0, ',', '.') }}
                            </td>
                        </tr>

                        @forelse($report['transactions'] as $t)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($t->date)->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-mono text-xs">JU-{{ str_pad($t->journal_id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-4 py-3">
                                    {{ $t->description }}
                                    @if($t->detail_description && $t->detail_description !== $t->description)
                                        <br><span class="text-xs text-gray-500">{{ $t->detail_description }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($t->debit > 0)
                                        Rp {{ number_format($t->debit, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($t->credit > 0)
                                        Rp {{ number_format($t->credit, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium">
                                    Rp {{ number_format($t->saldo, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="6">
                                    Tidak ada transaksi dalam periode ini
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold">
                        <tr>
                            <td class="px-4 py-3" colspan="3">TOTAL</td>
                            <td class="px-4 py-3 text-right text-green-600">
                                Rp {{ number_format($report['total_debit'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-red-600">
                                Rp {{ number_format($report['total_kredit'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right"></td>
                        </tr>
                        <tr class="bg-primary-50 dark:bg-primary-900/20">
                            <td class="px-4 py-3" colspan="5">SALDO AKHIR</td>
                            <td class="px-4 py-3 text-right text-lg">
                                Rp {{ number_format($report['saldo_akhir'], 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>