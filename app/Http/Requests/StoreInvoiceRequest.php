<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'invoice_number' => 'required|string|max:50|unique:invoices,invoice_number',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
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
            'client_id.required' => 'ID klien wajib diisi',
            'client_id.exists' => 'Klien tidak ditemukan',
            'project_id.exists' => 'Proyek tidak ditemukan',
            'invoice_number.required' => 'Nomor invoice wajib diisi',
            'invoice_number.unique' => 'Nomor invoice sudah digunakan',
            'invoice_date.required' => 'Tanggal invoice wajib diisi',
            'invoice_date.date' => 'Format tanggal invoice tidak valid',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi',
            'due_date.date' => 'Format tanggal jatuh tempo tidak valid',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo harus setelah atau sama dengan tanggal invoice',
            'subtotal.required' => 'Subtotal wajib diisi',
            'subtotal.numeric' => 'Subtotal harus berupa angka',
            'subtotal.min' => 'Subtotal tidak boleh negatif',
            'total_amount.required' => 'Total amount wajib diisi',
            'total_amount.numeric' => 'Total amount harus berupa angka',
            'total_amount.min' => 'Total amount tidak boleh negatif',
            'status.required' => 'Status invoice wajib diisi',
            'status.in' => 'Status invoice tidak valid',
            'items.required' => 'Item invoice wajib diisi',
            'items.array' => 'Item invoice harus berupa array',
            'items.min' => 'Minimal harus ada 1 item invoice',
        ];
    }
}