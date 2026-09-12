<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\repositories;

use Besnovatyj\Tags\entities\Tag;
use Besnovatyj\Tags\entities\TagAssignment;
use RuntimeException;
use Throwable;
use yii\data\ActiveDataProvider;
use yii\data\DataProviderInterface;
use yii\db\Exception;
use yii\db\StaleObjectException;

/**
 * Репозиторий словаря тегов для админки и сервисов записи.
 */
class TagRepository
{
    public function get(int $id): Tag
    {
        if (!$tag = Tag::findOne($id)) {
            throw new NotFoundException('Tag is not found.');
        }
        return $tag;
    }

    public function findBySlug(string $slug): ?Tag
    {
        return Tag::findOne(['slug' => $slug]);
    }

    /**
     * Теги по списку slug — одним запросом, ключ результата — slug.
     *
     * @param string[] $slugs
     * @return array<string, Tag>
     */
    public function findBySlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }
        return Tag::find()->andWhere(['slug' => $slugs])->indexBy('slug')->all();
    }

    /**
     * @throws Exception
     */
    public function save(Tag $tag): void
    {
        if (!$tag->save()) {
            throw new RuntimeException('Saving error.');
        }
    }

    /**
     * Удаление тега; связи уходят каскадом по внешнему ключу.
     *
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function remove(Tag $tag): void
    {
        if (!$tag->delete()) {
            throw new RuntimeException('Removing error.');
        }
    }

    /**
     * Теги без единой связи — кандидаты на удаление.
     */
    public function searchEmpty(): DataProviderInterface
    {
        $used = TagAssignment::find()->select(['tag_id']);
        return new ActiveDataProvider([
            'query' => Tag::find()->andWhere(['not in', 'id', $used]),
            'sort' => ['defaultOrder' => ['name' => SORT_ASC]],
        ]);
    }

    public function deleteEmpty(): int
    {
        $used = TagAssignment::find()->select(['tag_id']);
        return Tag::deleteAll(['not in', 'id', $used]);
    }
}
