<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\entities;

use yii\db\ActiveQuery;

/**
 * Связи `tagAssignments` и `tags` для AR-сущности контентного модуля.
 *
 * Подключается в сущность (`use TaggableEntityTrait;`) вместе с объявлением её ключа типа —
 * того же, что модуль отдаёт в {@see \Besnovatyj\Contracts\tags\TagSource}:
 *
 * ```php
 * class Post extends ActiveRecord
 * {
 *     use TaggableEntityTrait;
 *
 *     public static function tagType(): string { return 'blog.post'; }
 * }
 * ```
 *
 * Дальше — обычный AR: `Post::find()->with('tags')`, `$post->tags`. Связь через `onCondition` по
 * `entity_type`, поэтому жадная загрузка одной выборкой работает как с обычной таблицей связей.
 */
trait TaggableEntityTrait
{
    /**
     * Ключ вида записи для таблицы связей (`blog.post`).
     */
    abstract public static function tagType(): string;

    public function getTagAssignments(): ActiveQuery
    {
        return $this->hasMany(TagAssignment::class, ['entity_id' => 'id'])
            ->onCondition([TagAssignment::tableName() . '.entity_type' => static::tagType()]);
    }

    public function getTags(): ActiveQuery
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])
            ->via('tagAssignments')
            ->orderBy([Tag::tableName() . '.name' => SORT_ASC]);
    }
}
