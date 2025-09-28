<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => 'required_without:invoice_term_id|exists:invoices,id',
            'invoice_term_id' => 'required_without:invoice_id|exists:invoice_terms,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,credit_card,debit_card,paypal,other',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'status' => 'required|in:pending,completed,failed,refunded',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_id.required_without' => 'Invoice ID atau Invoice Term ID wajib diisi',
            'invoice_id.exists' => 'Invoice tidak ditemukan',
            'invoice_term_id.required_without' => 'Invoice ID atau Invoice Term ID wajib diisi',
            'invoice_term_id.exists' => 'Invoice term tidak ditemukan',
            'amount.required' => 'Jumlah pembayaran wajib diisi',
            'amount.numeric' => 'Jumlah pembayaran harus berupa angka',
            'amount.min' => 'Jumlah pembayaran minimal 0.01',
            'payment_date.required' => 'Tanggal pembayaran wajib diisi',
            'payment_date.date' => 'Format tanggal pembayaran tidak valid',
            'payment_method.required' => 'Metode pembayaran wajib diisi',
            'payment_method.in' => 'Metode pembayaran tidak valid',
            'reference_number.string' => 'Nomor referensi harus berupa teks',
            'reference_number.max' => 'Nomor referensi maksimal 100 karakter',
            'status.required' => 'Status pembayaran wajib diisi',
            'status.in' => 'Status pembayaran tidak valid',
        ];
    }
}