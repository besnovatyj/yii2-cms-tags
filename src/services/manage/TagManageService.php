<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\services\manage;

use Besnovatyj\Tags\entities\TagAssignment;
use Besnovatyj\Tags\forms\backend\TagForm;
use Besnovatyj\Tags\repositories\TagRepository;
use Besnovatyj\Tags\services\TaggableRegistry;
use Besnovatyj\Tags\services\TagAssigner;
use Throwable;
use Yii;
use yii\caching\TagDependency;
use yii\data\DataProviderInterface;
use yii\db\Exception;

/**
 * Админские операции над словарём тегов.
 *
 * Создания тега здесь нет намеренно: теги рождаются при сохранении записей через
 * {@see \Besnovatyj\Tags\forms\backend\TagsForm} → {@see TagAssigner}. В админке тег можно только
 * поправить (опечатка) и удалить.
 */
final class TagManageService
{
    public function __construct(
        private readonly TagRepository $tags,
        private readonly TaggableRegistry $registry,
    ) {
    }

    /**
     * @throws Exception
     */
    public function edit(int $id, TagForm $form): void
    {
        $tag = $this->tags->get($id);
        $tag->edit($form->name, $form->slug);
        $this->tags->save($tag);
        $this->invalidate();
    }

    /**
     * @throws Throwable
     */
    public function remove(int $id): void
    {
        $this->tags->remove($this->tags->get($id));
        $this->invalidate();
    }

    public function findEmpty(): DataProviderInterface
    {
        return $this->tags->searchEmpty();
    }

    public function deleteEmpty(): int
    {
        $count = $this->tags->deleteEmpty();
        $this->invalidate();
        return $count;
    }

    /**
     * Связи с типами, которых не объявляет ни один установленный модуль, — «ничьи».
     *
     * Сами по себе безвредны, но засоряют счётчики в админке. Остаются после удаления или
     * деактивации модуля; типы деактивированного модуля вернутся с его включением, поэтому
     * команда только показывает их, а удаление — отдельное осознанное действие ({@see pruneOrphans()}).
     *
     * @return array<string, int> тип → число связей
     */
    public function orphanTypes(): array
    {
        $known = array_keys($this->registry->sources());
        $rows = TagAssignment::find()
            ->select(['entity_type', 'cnt' => 'COUNT(*)'])
            ->andFilterWhere(['not in', 'entity_type', $known])
            ->groupBy('entity_type')
            ->asArray()
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['entity_type']] = (int)$row['cnt'];
        }
        return $result;
    }

    /**
     * Удалить связи «ничьих» типов. Записи с известными типами не трогаются: проверить, что
     * запись ещё существует, модуль тегов не может — это знает только её модуль.
     */
    public function pruneOrphans(): int
    {
        $types = array_keys($this->orphanTypes());
        if ($types === []) {
            return 0;
        }
        $count = TagAssignment::deleteAll(['entity_type' => $types]);
        $this->invalidate();
        return $count;
    }

    private function invalidate(): void
    {
        if (Yii::$app->has('cache')) {
            TagDependency::invalidate(Yii::$app->cache, [TagAssigner::CACHE_TAG]);
        }
    }
}
