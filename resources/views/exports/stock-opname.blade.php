<table>
    <thead>
        <tr>
            <th colspan="8" style="text-align: center; font-size: 16px;">LAPORAN STOCK OPNAME</th>
        </tr>
        <tr>
            <td colspan="8" style="text-align: center;">Tanggal Opname: {{ $opname->opname_date->format('d M Y') }}</td>
        </tr>
        <tr>
            <td colspan="8" style="text-align: center;">Gudang: {{ $opname->warehouse->name ?? '-' }} | Status: {{ $opname->status }}</td>
        </tr>
        <tr>
            <td colspan="8" style="text-align: center;">Dicetak: {{ now()->format('d M Y H:i') }}</td>
        </tr>
        <tr><td colspan="8"></td></tr>
        <tr style="background-color: #e2e8f0; font-weight: bold;">
            <th style="border: 1px solid #000;">No</th>
            <th style="border: 1px solid #000;">Kode Barang</th>
            <th style="border: 1px solid #000;">Nama Barang</th>
            <th style="border: 1px solid #000;">Stok Sistem</th>
            <th style="border: 1px solid #000;">Stok Fisik</th>
            <th style="border: 1px solid #000;">Selisih</th>
            <th style="border: 1px solid #000;">Status</th>
            <th style="border: 1px solid #000;">Catatan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($opname->details as $index => $detail)
            <tr>
                <td style="border: 1px solid #000; text-align: center;">{{ $index + 1 }}</td>
                <td style="border: 1px solid #000;">{{ $detail->product->code ?? '-' }}</td>
                <td style="border: 1px solid #000;">{{ $detail->product->name ?? '-' }}</td>
                <td style="border: 1px solid #000; text-align: right;">{{ $detail->system_qty }}</td>
                <td style="border: 1px solid #000; text-align: right;">{{ $detail->physical_qty }}</td>
                <td style="border: 1px solid #000; text-align: right;">{{ $detail->difference_qty }}</td>
                <td style="border: 1px solid #000; text-align: center;">
                    @if($detail->difference_qty == 0)
                        Sesuai
                    @elseif($detail->difference_qty > 0)
                        Surplus
                    @else
                        Minus
                    @endif
                </td>
                <td style="border: 1px solid #000;">{{ $detail->notes ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align: center; border: 1px solid #000;">Tidak ada data</td>
            </tr>
        @endforelse
    </tbody>
</table>