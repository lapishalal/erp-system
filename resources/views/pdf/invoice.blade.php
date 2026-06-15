<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Faktur {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #333; padding-bottom: 12px; }
        .header h2 { margin: 0; font-size: 20px; }
        .info { margin-bottom: 20px; width: 100%; }
        .info td { padding: 4px 0; vertical-align: top; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items th, .items td { border: 1px solid #333; padding: 8px; text-align: left; }
        .items th { background: #f5f5f5; font-weight: bold; }
        .items td.num { text-align: right; }
        .total { text-align: right; margin-top: 12px; font-size: 14px; }
        .total-row { font-weight: bold; }
        .footer { margin-top: 48px; text-align: center; font-size: 11px; color: #666; border-top: 1px solid #ddd; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>FAKTUR PENJUALAN</h2>
        <p>No: {{ $invoice->invoice_number }}</p>
    </div>

    <table class="info">
        <tr>
            <td width="60%">
                <strong>Kepada Yth:</strong><br>
                {{ $invoice->customer->name }}<<br>
                {{ $invoice->customer->address }}<<br>
                Telp: {{ $invoice->customer->phone ?? '-' }}
            </td>
            <td width="40%" style="text-align: right;">
                <strong>Tanggal:</strong> {{ $invoice->date->format('d M Y') }}<<br>
                <strong>Jatuh Tempo:</strong> {{ $invoice->due_date->format('d M Y') }}<<br>
                <strong>Status:</strong> {{ $invoice->status }}<<br>
                @if($invoice->salesOrder)
                <strong>SO:</strong> {{ $invoice->salesOrder->so_number }}
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:45%">Nama Barang</th>
                <th style="width:10%" class="num">Qty</th>
                <th style="width:20%" class="num">Harga</th>
                <th style="width:20%" class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->details as $i => $d)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $d->product->name }}</td>
                <td class="num">{{ $d->qty }}</td>
                <td class="num">Rp {{ number_format($d->price, 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format($d->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        <div>Total: Rp {{ number_format($invoice->total, 0, ',', '.') }}</div>
        <div>Dibayar: Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</div>
        <div class="total-row">Sisa: Rp {{ number_format($invoice->total - $invoice->paid_amount, 0, ',', '.') }}</div>
    </div>

    <div class="footer">
        Terima kasih atas kepercayaan Anda.<br>
        Faktur ini sah dan diproses secara komputerisasi.
    </div>
</body>
</html>