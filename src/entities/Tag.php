<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\entities;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Тег общего словаря.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 *
 * @property TagAssignment[] $assignments
 */
class Tag extends ActiveRecord
{
    public static function create(string $name, string $slug): self
    {
        $tag = new static();
        $tag->name = $name;
        $tag->slug = $slug;
        return $tag;
    }

    public function edit(string $name, string $slug): void
    {
        $this->name = $name;
        $this->slug = $slug;
    }

    public function getAssignments(): ActiveQuery
    {
        return $this->hasMany(TagAssignment::class, ['tag_id' => 'id']);
    }

    public static function tableName(): string
    {
        return '{{%tags}}';
    }
}
