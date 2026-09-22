<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Illuminate\Contracts\Validation\Validator;
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
            // Существование тегов проверяется не правилом exists, а одним
            // запросом в withValidator: exists выполняет отдельный SELECT на
            // каждый идентификатор, то есть пачка из 50 тегов стоила бы
            // 50 запросов только на валидацию.
            'tag_ids.*' => 'integer|distinct',
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
        ];
    }

    /**
     * Проверить существование всех тегов одним запросом.
     *
     * Ошибки привязываются к исходным индексам массива, поэтому клиент видит
     * ровно тот же формат, что дало бы правило exists (tag_ids.0, tag_ids.1 …).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('tag_ids');

            if (! is_array($ids) || $validator->errors()->has('tag_ids')) {
                return;
            }

            $numeric = array_filter($ids, static fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)));

            if ($numeric === []) {
                return;
            }

            $existing = Tag::whereIn('id', array_map('intval', $numeric))
                ->pluck('id')
                ->all();

            foreach ($numeric as $index => $id) {
                if (! in_array((int) $id, $existing, true)) {
                    $validator->errors()->add("tag_ids.{$index}", 'Тег с таким идентификатором не найден.');
                }
            }
        });
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
