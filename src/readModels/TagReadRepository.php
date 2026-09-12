<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\readModels;

use Besnovatyj\Tags\entities\Tag;
use Besnovatyj\Tags\entities\TagAssignment;

/**
 * Чтение тегов и связей для фронтенда и провайдеров.
 *
 * Знает только свои две таблицы: какие записи из связей видимы анониму, здесь не решается —
 * это отдаёт провайдер (см. {@see \Besnovatyj\Tags\services\TagPageService}). Поэтому методы возвращают
 * идентификаторы и сырые счётчики, а не «публичные» результаты.
 */
class TagReadRepository
{
    public function findBySlug(string $slug): ?Tag
    {
        return Tag::find()->andWhere(['slug' => $slug])->one();
    }

    /**
     * Теги одной записи.
     *
     * @return Tag[]
     */
    public function tagsOf(string $type, int $entityId): array
    {
        return Tag::find()->alias('t')
            ->innerJoin(['a' => TagAssignment::tableName()], 'a.tag_id = t.id')
            ->andWhere(['a.entity_type' => $type, 'a.entity_id' => $entityId])
            ->orderBy(['t.name' => SORT_ASC])
            ->all();
    }

    /**
     * Теги пачки записей одного типа одним запросом — для списков без N+1.
     *
     * @param int[] $entityIds
     * @return array<int, Tag[]> ключ — id записи; записи без тегов в результате отсутствуют
     */
    public function tagsOfMany(string $type, array $entityIds): array
    {
        if ($entityIds === []) {
            return [];
        }

        $rows = Tag::find()->alias('t')
            ->select(['t.*', 'a.entity_id'])
            ->innerJoin(['a' => TagAssignment::tableName()], 'a.tag_id = t.id')
            ->andWhere(['a.entity_type' => $type, 'a.entity_id' => $entityIds])
            ->orderBy(['t.name' => SORT_ASC])
            ->asArray()
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $entityId = (int)$row['entity_id'];
            unset($row['entity_id']);
            $tag = new Tag();
            Tag::populateRecord($tag, $row);
            $result[$entityId][] = $tag;
        }
        return $result;
    }

    /**
     * Идентификаторы записей с тегом, сгруппированные по типу. Видимость НЕ учтена.
     *
     * @return array<string, int[]> ключ — `entity_type`
     */
    public function entityIdsByTag(Tag $tag, ?string $type = null): array
    {
        $query = TagAssignment::find()
            ->select(['entity_type', 'entity_id'])
            ->andWhere(['tag_id' => $tag->id])
            ->andFilterWhere(['entity_type' => $type])
            ->orderBy(['entity_id' => SORT_DESC])
            ->asArray();

        $result = [];
        foreach ($query->each(500) as $row) {
            $result[$row['entity_type']][] = (int)$row['entity_id'];
        }
        return $result;
    }

    /**
     * Теги с наибольшим числом связей — кандидаты в облако. Видимость НЕ учтена: это первый,
     * дешёвый отсев (один GROUP BY), окончательный отбор делает {@see \Besnovatyj\Tags\services\PopularTagsService}
     * после фильтрации провайдерами.
     *
     * @return int[] id тегов по убыванию числа связей
     */
    public function topTagIdsByRawCount(int $limit): array
    {
        $rows = TagAssignment::find()
            ->select(['tag_id', 'cnt' => 'COUNT(*)'])
            ->groupBy('tag_id')
            ->orderBy(['cnt' => SORT_DESC, 'tag_id' => SORT_ASC])
            ->limit($limit)
            ->asArray()
            ->all();

        return array_map(static fn (array $row): int => (int)$row['tag_id'], $rows);
    }

    /**
     * Связи указанных тегов, сгруппированные по типу и тегу. Видимость НЕ учтена.
     *
     * @param int[] $tagIds
     * @return array<string, array<int, int[]>> `[entity_type => [tag_id => [entity_id, ...]]]`
     */
    public function assignmentsOfTags(array $tagIds): array
    {
        if ($tagIds === []) {
            return [];
        }

        $query = TagAssignment::find()
            ->select(['tag_id', 'entity_type', 'entity_id'])
            ->andWhere(['tag_id' => $tagIds])
            ->asArray();

        $result = [];
        foreach ($query->each(1000) as $row) {
            $result[$row['entity_type']][(int)$row['tag_id']][] = (int)$row['entity_id'];
        }
        return $result;
    }

    /**
     * @param int[] $ids
     * @return array<int, Tag> ключ — id тега
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        return Tag::find()->andWhere(['id' => $ids])->indexBy('id')->all();
    }
}
