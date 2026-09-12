<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\entities;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Связь тега с записью модуля. Запись адресуется парой `entity_type` + `entity_id`
 * (см. миграцию — почему без внешнего ключа на таблицу записи).
 *
 * @property int    $tag_id
 * @property string $entity_type
 * @property int    $entity_id
 *
 * @property Tag $tag
 */
class TagAssignment extends ActiveRecord
{
    public static function create(int $tagId, string $entityType, int $entityId): self
    {
        $assignment = new static();
        $assignment->tag_id = $tagId;
        $assignment->entity_type = $entityType;
        $assignment->entity_id = $entityId;
        return $assignment;
    }

    public function getTag(): ActiveQuery
    {
        return $this->hasOne(Tag::class, ['id' => 'tag_id']);
    }

    public static function tableName(): string
    {
        return '{{%tags_assignments}}';
    }
}
