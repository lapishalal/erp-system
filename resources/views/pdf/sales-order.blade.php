<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Order {{ $so->so_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; }
        .info { margin-bottom: 20px; }
        .info table { width: 100%; }
        .info td { padding: 5px; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items th, .items td { border: 1px solid #000; padding: 8px; text-align: left; }
        .items th { background: #f0f0f0; }
        .total { text-align: right; font-size: 14px; font-weight: bold; }
        .status { padding: 5px 10px; border-radius: 3px; display: inline-block; }
        .status-DRAFT { background: #ccc; }
        .status-OPEN { background: #3498db; color: white; }
        .status-PARTIAL { background: #f39c12; color: white; }
        .status-COMPLETE { background: #2ecc71; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h2>SALES ORDER</h2>
        <p>No: {{ $so->so_number }}</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td width="50%">
                    <strong>Customer:</strong><br>
                    {{ $so->customer->name }}<<br>
                    {{ $so->customer->address }}
                </td>
                <td width="50%" style="text-align: right;">
                    <strong>Tanggal:</strong> {{ $so->date->format('d M Y') }}<<br>
                    <strong>Status:</strong> <span class="status status-{{ $so->status }}">{{ $so->status }}</span>
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>No</th>
                <th>Produk</th>
                <th>Brand</th>
                <th>Qty</th>
                <th>Harga</th>
                <th>Subtotal</th>
                <th>Delivered</th>
                <th>Remaining</th>
            </tr>
        </thead>
        <tbody>
            @foreach($so->details as $i => $d)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $d->product->name }}</td>
                <td>{{ $d->product->brand->name ?? '-' }}</td>
                <td>{{ $d->qty }}</td>
                <td>Rp {{ number_format($d->unit_price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($d->subtotal, 0, ',', '.') }}</td>
                <td>{{ $d->delivered_qty }}</td>
                <td>{{ $d->remaining_qty }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        Total Qty: {{ $so->total_qty }} | Total Omset: Rp {{ number_format($so->total_amount, 0, ',', '.') }} | Profit: Rp {{ number_format($so->profit, 0, ',', '.') }}
    </div>

    @if($so->deliveryOrders->count() > 0)
    <h3>Surat Jalan:</h3>
    <ul>
        @foreach($so->deliveryOrders as $do)
        <li>{{ $do->do_number }} - {{ $do->date->format('d M Y') }} - {{ $do->status }} - Qty: {{ $do->total_qty }}</li>
        @endforeach
    </ul>
    @endif
</body>
</html>