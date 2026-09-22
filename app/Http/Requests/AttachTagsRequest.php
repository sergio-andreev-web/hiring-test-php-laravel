<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachTagsRequest extends FormRequest
{
    public function authorize()
    {
        // Право на изменение тегов проверяет PostPolicy::manageTags,
        // подключённая к маршруту через ->can(). Здесь — только валидация.
        return true;
    }

    public function rules()
    {
        return [
            // max ограничивает размер пачки: без верхней границы один запрос
            // может выродиться в неограниченную вставку.
            'tag_ids' => 'required|array|min:1|max:50',
            'tag_ids.*' => 'integer|distinct|exists:tags,id',
        ];
    }

    public function messages()
    {
        return [
            'tag_ids.required' => 'Нужно передать список идентификаторов тегов.',
            'tag_ids.array' => 'Поле tag_ids должно быть массивом.',
            'tag_ids.min' => 'Нужно передать хотя бы один тег.',
            'tag_ids.max' => 'За один запрос можно привязать не более 50 тегов.',
            'tag_ids.*.integer' => 'Идентификатор тега должен быть целым числом.',
            'tag_ids.*.distinct' => 'Идентификаторы тегов не должны повторяться.',
            'tag_ids.*.exists' => 'Тег с таким идентификатором не найден.',
        ];
    }

    /**
     * Идентификаторы тегов, прошедшие валидацию.
     *
     * @return array<int, int>
     */
    public function tagIds(): array
    {
        return array_map('intval', $this->validated('tag_ids'));
    }
}
