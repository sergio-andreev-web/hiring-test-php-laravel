<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreTagRequest extends FormRequest
{
    public function authorize()
    {
        // Теги — общий справочник, владельца у них нет.
        // Достаточно аутентификации, она навешена на маршрут.
        return true;
    }

    /**
     * Если slug не прислали — строим его из названия.
     *
     * Str::slug умеет латиницу и кириллицу, но на иероглифах или строке из
     * одной пунктуации возвращает пустую строку. В этом случае slug намеренно
     * не подставляем: сработает правило required и клиент получит внятную
     * ошибку вместо тега с пустым slug.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('slug') || ! $this->filled('name')) {
            return;
        }

        $slug = Str::slug((string) $this->input('name'));

        if ($slug !== '') {
            $this->merge(['slug' => $slug]);
        }
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                // Строчная латиница, цифры и одиночные дефисы. Намеренно строго:
                // невалидный slug отклоняем, а не «чиним» молча за клиента.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:tags,slug',
            ],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Название тега требуется.',
            'slug.required' => 'Укажите slug: из названия собрать его не удалось.',
            'slug.regex' => 'Slug может состоять только из строчной латиницы, цифр и одиночных дефисов.',
            'slug.unique' => 'Тег с таким slug уже существует.',
        ];
    }
}
