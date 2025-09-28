<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
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
            'amount' => 'sometimes|numeric|min:0.01',
            'payment_date' => 'sometimes|date',
            'payment_method' => 'sometimes|in:cash,bank_transfer,credit_card,debit_card,paypal,other',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:pending,completed,failed,refunded',
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
            'amount.numeric' => 'Jumlah pembayaran harus berupa angka',
            'amount.min' => 'Jumlah pembayaran minimal 0.01',
            'payment_date.date' => 'Format tanggal pembayaran tidak valid',
            'payment_method.in' => 'Metode pembayaran tidak valid',
            'reference_number.string' => 'Nomor referensi harus berupa teks',
            'reference_number.max' => 'Nomor referensi maksimal 100 karakter',
            'status.in' => 'Status pembayaran tidak valid',
        ];
    }
}