<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\forms\backend;

use Besnovatyj\Forms\BaseForm;
use Besnovatyj\Helpers\StringHelper;
use Besnovatyj\Tags\entities\Tag;
use Besnovatyj\Validators\SlugValidator;
use yii\helpers\Inflector;

/**
 * Форма одного тега — единственное место, где тег валидируется и где из имени выводится slug.
 *
 * Используется двумя путями: редактирование тега в админке (`new TagForm($tag)`) и валидация
 * нового тега при назначении записи ({@see TagsForm} создаёт её на каждое незнакомое имя).
 * Создания тега в админке нет — см. {@see \Besnovatyj\Tags\services\manage\TagManageService}.
 */
class TagForm extends BaseForm
{
    public string $name = '';
    public string $slug = '';

    private ?Tag $_tag;

    public function __construct(?Tag $tag = null, $config = [])
    {
        if ($tag) {
            $this->name = $tag->name;
            $this->slug = $tag->slug;
        }
        $this->_tag = $tag;
        parent::__construct($config);
    }

    /**
     * Slug из имени — одна реализация на пакет: её же использует {@see TagsForm} для поиска
     * существующего тега и импорт legacy-таблиц.
     */
    public static function slugFor(string $name): string
    {
        return Inflector::slug(StringHelper::spaceReplace($name));
    }

    public function beforeValidate(): bool
    {
        $this->name = StringHelper::spaceReplace($this->name);
        $this->slug = $this->slug !== '' ? Inflector::slug($this->slug) : self::slugFor($this->name);
        return parent::beforeValidate();
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name', 'slug'], 'string', 'max' => 255],
            ['slug', SlugValidator::class],
            [['name', 'slug'], 'unique', 'targetClass' => Tag::class,
                'filter' => $this->_tag ? ['<>', 'id', $this->_tag->id] : null],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Название',
            'slug' => 'Slug',
        ];
    }

    public function isNew(): bool
    {
        return $this->_tag === null;
    }
}
