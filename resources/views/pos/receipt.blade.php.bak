<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk {{ $pos->transaction_number }}</title>
    <style>
        @media print {
            body { width: 80mm; margin: 0; padding: 0; }
            .no-print { display: none; }
        }
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 80mm; margin: 0 auto; padding: 8px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; }
        .item-name { max-width: 50mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="center bold" style="font-size: 14px;">{{ config('app.name', 'ERP System') }}</div>
    <div class="center">Struk Pembayaran</div>
    <div class="center" style="font-size: 10px; margin-bottom: 8px;">{{ $pos->date->format('d M Y H:i') }}</div>

    <div>No: {{ $pos->transaction_number }}</div>
    <div>Kasir: {{ $pos->creator?->name ?? '-' }}</div>
    @if($pos->customer)
    <div>Customer: {{ $pos->customer->name }}</div>
    @endif

    <div class="line"></div>

    <table>
        @foreach($pos->details as $d)
        <tr>
            <td colspan="2" class="item-name">{{ $d->product->name }}</td>
        </tr>
        <tr>
            <td>{{ $d->qty }} x {{ number_format($d->price, 0, ',', '.') }}</td>
            <td class="right">{{ number_format($d->subtotal, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <div class="line"></div>

    <table>
        <tr><td>Subtotal</td><td class="right">{{ number_format($pos->subtotal, 0, ',', '.') }}</td></tr>
        @if($pos->discount > 0)
        <tr><td>Diskon</td><td class="right">{{ number_format($pos->discount, 0, ',', '.') }}</td></tr>
        @endif
        @if($pos->tax > 0)
        <tr><td>Pajak</td><td class="right">{{ number_format($pos->tax, 0, ',', '.') }}</td></tr>
        @endif
        <tr class="bold"><td>TOTAL</td><td class="right">{{ number_format($pos->total, 0, ',', '.') }}</td></tr>
        <tr><td>Bayar</td><td class="right">{{ number_format($pos->paid_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Kembalian</td><td class="right">{{ number_format($pos->change_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Metode</td><td class="right">{{ $pos->payment_method }}</td></tr>
    </table>

    <div class="line"></div>
    <div class="center" style="font-size: 10px;">Terima kasih atas kunjungan Anda</div>

    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">🖨️ Print Struk</button>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 300);
        }
    </script>
</body>
</html>