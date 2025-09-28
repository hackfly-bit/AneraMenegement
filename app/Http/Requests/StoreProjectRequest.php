<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'status' => 'required|in:planning,in_progress,on_hold,completed,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'progress' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string',
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
            'name.required' => 'Nama proyek wajib diisi',
            'start_date.required' => 'Tanggal mulai wajib diisi',
            'start_date.date' => 'Format tanggal mulai tidak valid',
            'end_date.date' => 'Format tanggal selesai tidak valid',
            'end_date.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai',
            'budget.numeric' => 'Anggaran harus berupa angka',
            'budget.min' => 'Anggaran tidak boleh negatif',
            'status.required' => 'Status proyek wajib diisi',
            'status.in' => 'Status proyek tidak valid',
            'priority.required' => 'Prioritas proyek wajib diisi',
            'priority.in' => 'Prioritas proyek tidak valid',
            'progress.integer' => 'Progress harus berupa angka bulat',
            'progress.min' => 'Progress minimal 0',
            'progress.max' => 'Progress maksimal 100',
        ];
    }
}