<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string|min:10',
            'status' => 'nullable|in:draft,published',
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'Заголовок требуется.',
            'body.required' => 'Содержание требуется.',
            'body.min' => 'Содержание должно быть не менее 10 символов.',
        ];
    }
}
