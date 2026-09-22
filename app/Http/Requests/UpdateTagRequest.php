<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    /**
     * Здесь slug из названия намеренно не пересобирается: slug — часть
     * публичного адреса тега, и менять его молча при переименовании значит
     * ломать внешние ссылки. Хочешь сменить — пришли явно.
     */
    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('tags', 'slug')->ignore($this->route('tag')),
            ],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Название тега требуется.',
            'slug.required' => 'Slug не может быть пустым.',
            'slug.regex' => 'Slug может состоять только из строчной латиницы, цифр и одиночных дефисов.',
            'slug.unique' => 'Тег с таким slug уже существует.',
        ];
    }
}
