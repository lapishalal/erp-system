<x-filament-panels::page>
    <div class="mb-4">
        <x-filament::section>
            <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                <p class="font-semibold text-gray-800 dark:text-white">Apa itu Review Settlement?</p>
                <p>Pesanan di bawah ini memiliki <strong>jumlah penyelesaian pembayaran Rp 0</strong> pada file Income TikTok. Artinya uang tidak cair — kemungkinan besar pesanan <strong>retur</strong> atau <strong>dibatalkan</strong>.</p>
                <ul class="list-disc list-inside space-y-0.5 ml-2">
                    <li><strong>Retur</strong> → pilih jika barang sudah diterima kembali dari ekspedisi. SO/DO/Invoice dibatalkan & stok otomatis dikembalikan.</li>
                    <li><strong>Cancel</strong> → pilih jika pesanan batal. Tidak ada dokumen yang diubah, pesanan hanya disembunyikan.</li>
                    <li>Cek manual ke ekspedisi dulu sebelum memutuskan.</li>
                </ul>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>