<?php

namespace App\Imports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CustomerImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): ?Customer
    {
        if (!empty($row['kode']) && Customer::where('code', $row['kode'])->exists()) {
            return null;
        }
        if (!empty($row['email']) && Customer::where('email', $row['email'])->exists()) {
            return null;
        }

        return new Customer([
            'code'          => $row['kode'] ?? 'CUST-' . strtoupper(uniqid()),
            'name'          => $row['nama'],
            'address'       => $row['alamat'] ?? null,
            'phone'         => $row['telepon'] ?? null,
            'email'         => $row['email'] ?? null,
            'pic'           => $row['pic'] ?? null,
            'credit_limit'  => $row['limit_kredit'] ?? null,
            'is_active'     => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:255',
            'kode' => 'nullable|string|max:50',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'pic' => 'nullable|string|max:255',
            'limit_kredit' => 'nullable|numeric',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nama.required' => 'Kolom NAMA wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ];
    }
}