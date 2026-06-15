<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Slip Gaji {{ $payroll->payroll_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; width: 210mm; margin: 0 auto; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 18px; }
        .info { margin-bottom: 20px; }
        .info table { width: 100%; }
        .info td { padding: 4px 0; }
        .section-title { background: #f5f5f5; padding: 8px; font-weight: bold; margin: 15px 0 5px 0; border-left: 4px solid #333; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .items td { padding: 6px; border-bottom: 1px solid #eee; }
        .items td.num { text-align: right; }
        .total { font-weight: bold; border-top: 2px solid #333; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #666; }
        .signature { margin-top: 60px; display: flex; justify-content: space-between; }
        .signature-box { width: 150px; text-align: center; }
        .signature-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>SLIP GAJI KARYAWAN</h2>
        <p>{{ config('app.name', 'ERP System') }} | Periode: {{ $payroll->payrollPeriod->period_name }}</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td width="50%"><strong>Nama:</strong> {{ $payroll->employee->name }}</td>
                <td width="50%"><strong>NIK:</strong> {{ $payroll->employee->nik }}</td>
            </tr>
            <tr>
                <td><strong>Jabatan:</strong> {{ $payroll->employee->position ?? '-' }}</td>
                <td><strong>Divisi:</strong> {{ $payroll->employee->department ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>No. Slip:</strong> {{ $payroll->payroll_number }}</td>
                <td><strong>Status:</strong> {{ $payroll->status }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">A. PENGHASILAN</div>
    <table class="items">
        <tr>
            <td>Gaji Pokok</td>
            <td class="num">Rp {{ number_format($payroll->basic_salary, 0, ',', '.') }}</td>
        </tr>
        @foreach($payroll->details->where('type', 'EARNING') as $d)
            @if($d->name != 'Gaji Pokok')
            <tr>
                <td>{{ $d->name }}</td>
                <td class="num">Rp {{ number_format($d->amount, 0, ',', '.') }}</td>
            </tr>
            @endif
        @endforeach
        <tr class="total">
            <td>Total Penghasilan</td>
            <td class="num">Rp {{ number_format($payroll->total_earnings, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">B. BPJS DITANGGUNG PERUSAHAAN</div>
    <table class="items">
        @foreach($payroll->details->where('type', 'BPJS_COMPANY') as $d)
        <tr>
            <td>{{ $d->name }}</td>
            <td class="num">Rp {{ number_format($d->amount, 0, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td>Total BPJS Perusahaan</td>
            <td class="num">Rp {{ number_format($payroll->total_bpjs_company, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">C. POTONGAN</div>
    <table class="items">
        @foreach($payroll->details->where('type', 'BPJS_EMPLOYEE') as $d)
        <tr>
            <td>{{ $d->name }}</td>
            <td class="num">(Rp {{ number_format($d->amount, 0, ',', '.') }})</td>
        </tr>
        @endforeach
        <tr class="total">
            <td>Total Potongan</td>
            <td class="num">(Rp {{ number_format($payroll->total_deductions, 0, ',', '.') }})</td>
        </tr>
    </table>

    <div class="section-title">D. RINGKASAN</div>
    <table class="items">
        <tr>
            <td><strong>Gross Salary (Beban Perusahaan)</strong></td>
            <td class="num"><strong>Rp {{ number_format($payroll->gross_salary, 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td><strong>Gaji Bersih (Diterima)</strong></td>
            <td class="num"><strong style="font-size: 14px;">Rp {{ number_format($payroll->net_salary, 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    <div class="signature">
        <div class="signature-box">
            <div class="signature-line">Diterima Oleh</div>
            <div>{{ $payroll->employee->name }}</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Hormat Kami</div>
            <div>{{ config('app.name') }}</div>
        </div>
    </div>

    <div class="footer">
        Slip gaji ini sah dan diproses secara komputerisasi.<br>
        Dicetak pada: {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>