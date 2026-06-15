<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Jalan {{ $do->do_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h2 { margin: 0; }
        .info { margin-bottom: 20px; }
        .info table { width: 100%; }
        .info td { padding: 5px; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items th, .items td { border: 1px solid #000; padding: 8px; text-align: left; }
        .items th { background: #f0f0f0; }
        .signature { margin-top: 50px; }
        .signature table { width: 100%; }
        .signature td { text-align: center; padding-top: 50px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>SURAT JALAN</h2>
        <p>No: {{ $do->do_number }}</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td width="50%">
                    <strong>Kepada Yth:</strong><br>
                    {{ $do->customer->name }}<<br>
                    {{ $do->customer->address }}
                </td>
                <td width="50%" style="text-align: right;">
                    <strong>Tanggal:</strong> {{ $do->date->format('d M Y') }}<<br>
                    <strong>SO Ref:</strong> {{ $do->salesOrder->so_number ?? '-' }}<<br>
                    <strong>Driver:</strong> {{ $do->driver ?? '-' }}<<br>
                    <strong>Kendaraan:</strong> {{ $do->vehicle ?? '-' }}
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>No</th>
                <th>Produk</th>
                <th>Qty</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($do->details as $i => $d)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $d->product->name }}</td>
                <td>{{ $d->qty }}</td>
                <td>{{ $d->notes ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        <strong>Total Qty: {{ $do->total_qty }}</strong>
    </div>

    <div class="signature">
        <table>
            <tr>
                <td width="33%">
                    Dibuat Oleh,<br><br><br>
                    (________________)
                </td>
                <td width="33%">
                    Driver,<br><br><br>
                    (________________)
                </td>
                <td width="33%">
                    Diterima Oleh,<br><br><br>
                    (________________)
                </td>
            </tr>
        </table>
    </div>
</body>
</html>