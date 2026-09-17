<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'price'   => ['required', 'integer', 'min:1'],
            'months'  => ['required', 'integer', 'min:1', 'max:60'],
            'advance' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
