<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Surat Jalan {{ $do->do_number }}</title>
<style>
body { font-family: Arial, sans-serif; font-size: 12px; }
.header { text-align: center; margin-bottom: 20px; }
.header h2 { margin: 0; font-size: 18px; }
.header p { margin: 2px 0; font-size: 11px; color: #555; }
.info-table { width: 100%; margin-bottom: 20px; }
.info-table td { padding: 4px; vertical-align: top; }
.info-table .label { font-weight: bold; width: 120px; }
.items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.items-table th, .items-table td { border: 1px solid #333; padding: 6px; text-align: left; }
.items-table th { background: #f0f0f0; }
.items-table .right { text-align: right; }
.footer { margin-top: 40px; }
.footer-table { width: 100%; }
.footer-table td { text-align: center; vertical-align: top; padding-top: 60px; }
.signature-line { border-top: 1px solid #333; width: 150px; margin: 0 auto; padding-top: 5px; }
</style>
</head>
<body>
<div class="header">
    @if($company?->logo)
        <img src="{{ public_path('storage/' . $company->logo) }}" style="max-height: 60px; margin-bottom: 10px;">
    @endif
    <h2>{{ $company?->company_name ?? 'PT. XXX' }}</h2>
    <p>{{ $company?->address ?? '-' }}</p>
    <p>Telp: {{ $company?->phone ?? '-' }} | Email: {{ $company?->email ?? '-' }}</p>
    <hr>
    <h3 style="margin-top: 10px;">SURAT JALAN</h3>
</div>

<table class="info-table">
    <tr><td class="label">Nomor SJ</td><td>: {{ $do->do_number }}</td><td class="label">Tanggal</td><td>: {{ $do->date->format('d M Y') }}</td></tr>
    <tr><td class="label">SO</td><td>: {{ $do->salesOrder?->so_number ?? '-' }}</td><td class="label">Customer</td><td>: {{ $do->customer?->name ?? '-' }}</td></tr>
    <tr><td class="label">Driver</td><td>: {{ $do->driver ?? '-' }}</td><td class="label">Kendaraan</td><td>: {{ $do->vehicle ?? '-' }}</td></tr>
</table>

<table class="items-table">
    <thead><tr><th>No</th><th>Barang</th><th class="right">Qty</th></tr></thead>
    <tbody>
        @foreach($do->details as $i => $d)
        <tr><td>{{ $i + 1 }}</td><td>{{ $d->product?->name ?? '-' }}</td><td class="right">{{ number_format($d->qty, 0, ',', '.') }}</td></tr>
        @endforeach
    </tbody>
    <tfoot><tr><th colspan="2" class="right">Total Qty</th><th class="right">{{ number_format($do->total_qty, 0, ',', '.') }}</th></tr></tfoot>
</table>

<p><strong>Catatan:</strong> {{ $do->notes ?? '-' }}</p>

<div class="footer">
    <table class="footer-table">
        <tr>
            <td><p>Dibuat oleh,</p><div class="signature-line">{{ $do->creator?->name ?? '-' }}</div></td>
            <td><p>Penerima,</p><div class="signature-line">.........................</div></td>
            <td><p>Mengetahui,</p>@if($company?->signature_image)<img src="{{ public_path('storage/' . $company->signature_image) }}" style="max-height: 50px; margin-bottom: 5px;">@endif<div class="signature-line">{{ $company?->signature_name ?? '.........................' }}</div></td>
        </tr>
    </table>
</div>
</body>
</html>