<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\services;

use Besnovatyj\Tags\entities\Tag;
use Besnovatyj\Tags\entities\TagAssignment;
use Besnovatyj\Tags\forms\backend\TagForm;
use Besnovatyj\Tags\repositories\TagRepository;
use RuntimeException;
use Yii;
use yii\caching\TagDependency;
use yii\db\Exception;

/**
 * Назначение тегов записи — единственная точка записи в таблицу связей.
 *
 * Вызывается сервисом контентного модуля внутри его транзакции (одно соединение — та же
 * транзакция), с уже провалидированными {@see TagForm} из {@see \Besnovatyj\Tags\forms\backend\TagsForm}.
 * Недостающие теги создаёт, лишние связи снимает, существующие не трогает — `sync`, а не
 * «удалить всё и записать заново», чтобы связи не мигали при каждом сохранении.
 *
 * Сюда же — снятие всех тегов при удалении записи: внешнего ключа на таблицу записи нет, и
 * забыть `detachAll()` в сервисе модуля означает оставить сироты (безвредные, чистятся `tags/prune`).
 */
final class TagAssigner
{
    /** Тег кэша, который сбрасывает любое изменение связей (облако, счётчики). */
    public const string CACHE_TAG = 'tags';

    public function __construct(private readonly TagRepository $tags)
    {
    }

    /**
     * Привести теги записи к списку форм: чего нет — создать и привязать, чего нет в списке — отвязать.
     *
     * @param TagForm[] $items провалидированные формы тегов (существующих и новых)
     * @throws Exception
     */
    public function sync(string $type, int $entityId, array $items): void
    {
        $wanted = [];
        foreach ($items as $form) {
            $wanted[$form->slug] = $form;
        }

        $existing = $this->tags->findBySlugs(array_keys($wanted));
        $wantedIds = [];
        foreach ($wanted as $slug => $form) {
            $tag = $existing[$slug] ?? null;
            if ($tag === null) {
                $tag = Tag::create($form->name, $form->slug);
                $this->tags->save($tag);
            }
            $wantedIds[(int)$tag->id] = true;
        }

        $current = TagAssignment::find()
            ->select('tag_id')
            ->andWhere(['entity_type' => $type, 'entity_id' => $entityId])
            ->column();
        $current = array_fill_keys(array_map('intval', $current), true);

        $toRemove = array_keys(array_diff_key($current, $wantedIds));
        if ($toRemove !== []) {
            TagAssignment::deleteAll(['entity_type' => $type, 'entity_id' => $entityId, 'tag_id' => $toRemove]);
        }

        $toAdd = array_keys(array_diff_key($wantedIds, $current));
        foreach ($toAdd as $tagId) {
            if (!TagAssignment::create($tagId, $type, $entityId)->save()) {
                throw new RuntimeException('Failed to save tag assignment.');
            }
        }

        if ($toRemove !== [] || $toAdd !== []) {
            $this->invalidate();
        }
    }

    /**
     * Снять все теги с записи — при её удалении.
     */
    public function detachAll(string $type, int $entityId): void
    {
        if (TagAssignment::deleteAll(['entity_type' => $type, 'entity_id' => $entityId]) > 0) {
            $this->invalidate();
        }
    }

    private function invalidate(): void
    {
        if (Yii::$app->has('cache')) {
            TagDependency::invalidate(Yii::$app->cache, [self::CACHE_TAG]);
        }
    }
}
