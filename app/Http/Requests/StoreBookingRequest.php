<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_email' => ['required', 'email:rfc', 'max:254'],
        ];
    }

    public function messages(): array
    {
        return [
            'slot_id.required' => 'Выберите время встречи.',
            'slot_id.integer' => 'Некорректный идентификатор слота.',
            'slot_id.exists' => 'Такого слота нет в расписании.',
            'customer_name.required' => 'Укажите ваше имя.',
            'customer_name.string' => 'Имя должно быть строкой.',
            'customer_name.max' => 'Имя должно быть не длиннее 100 символов.',
            'customer_email.required' => 'Укажите email.',
            'customer_email.email' => 'Введите корректный email.',
            'customer_email.max' => 'Email должен быть не длиннее 254 символов.',
        ];
    }
}
