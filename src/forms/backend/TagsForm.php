<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\forms\backend;

use Besnovatyj\Forms\BaseForm;
use Besnovatyj\Helpers\StringHelper;
use Besnovatyj\Tags\entities\Tag;
use yii\helpers\ArrayHelper;

/**
 * Форма НАЗНАЧЕНИЯ тегов записи — вложенная форма композитной формы контентного модуля.
 *
 * Транспорт — плоский список имён из Select2 (`newTagsNames[]`), результат — список {@see TagForm}
 * в `items`, который сервис модуля отдаёт в {@see \Besnovatyj\Tags\services\TagAssigner::sync()}.
 * Композиция как в `CompositeForm`, но вложенные формы рождаются из данных, а не из
 * `internalForms()`: сколько будет тегов, до `load()` неизвестно.
 *
 * Каждое имя: тег с таким slug уже есть → берётся как есть (старый тег, не проходящий нынешние
 * правила, не должен блокировать сохранение записи — чинится на своей странице); нет → новый
 * {@see TagForm} проходит полную валидацию (required, длина, SlugValidator, уникальность).
 * Ошибка вешается на `newTagsNames`, поэтому виджет её показывает.
 *
 * Подключение в форме модуля:
 * ```php
 * $this->tags = new TagsForm($post?->tags ?? []);        // в конструкторе CompositeForm
 * protected function internalForms(): array { return ['meta', 'tags']; }
 * $form->field($model->tags, 'newTagsNames')->widget(Select2Widget::class, [
 *     'endpoint' => Url::to(['/Tags/backend/tag/search-endpoint'], true),
 * ]);
 * ```
 */
class TagsForm extends BaseForm
{
    /** @var string[] имена тегов из виджета */
    public array $newTagsNames = [];

    /** @var TagForm[] результат валидации: формы существующих и новых тегов, по одной на имя */
    public array $items = [];

    /**
     * @param Tag[] $current текущие теги записи (для формы редактирования)
     */
    public function __construct(array $current = [], $config = [])
    {
        $this->newTagsNames = array_values(ArrayHelper::getColumn($current, 'name'));
        parent::__construct($config);
    }

    public function beforeValidate(): bool
    {
        $names = [];
        foreach ($this->newTagsNames as $name) {
            $name = StringHelper::spaceReplace((string)$name);
            if ($name !== '') {
                $names[TagForm::slugFor($name)] ??= $name; // дубликаты по slug схлопываем, первый выигрывает
            }
        }
        $this->newTagsNames = array_values($names);
        $this->items = [];
        return parent::beforeValidate();
    }

    public function rules(): array
    {
        return [
            ['newTagsNames', 'validateTags'],
        ];
    }

    public function validateTags(string $attribute): void
    {
        $slugs = array_map(TagForm::slugFor(...), $this->newTagsNames);
        $existing = $slugs === [] ? [] : Tag::find()->andWhere(['slug' => $slugs])->indexBy('slug')->all();

        foreach ($this->newTagsNames as $i => $name) {
            $tag = $existing[$slugs[$i]] ?? null;
            $form = new TagForm($tag);
            if ($tag === null) {
                $form->name = $name;
                if (!$form->validate()) {
                    $this->addError($attribute, "«{$name}»: " . implode(' ', $form->getFirstErrors()));
                    continue;
                }
            }
            $this->items[] = $form;
        }
    }

    public function attributeLabels(): array
    {
        return [
            'newTagsNames' => 'Теги',
        ];
    }
}
