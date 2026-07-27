<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'topic' => ['nullable', 'string', 'max:255'],
        ];
    }
}
