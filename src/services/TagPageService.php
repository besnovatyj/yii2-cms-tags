<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\services;

use Besnovatyj\Contracts\tags\TaggedItem;
use Besnovatyj\Tags\entities\Tag;
use Besnovatyj\Tags\readModels\TagReadRepository;
use Besnovatyj\Tags\results\TagFacet;
use Besnovatyj\Tags\results\TagPage;
use Besnovatyj\Tags\settings\TagSettings;

/**
 * Сборка страницы тега `/tag/<slug>`.
 *
 * Модуль тегов знает только id записей; что из них видно и как выглядит карточка — отдаёт
 * провайдер. Порядок: связи тега по типам → провайдеры отсекают невидимое (это и есть счётчики
 * вкладок) → срез текущей страницы → карточки только для среза. Записи внутри типа идут по
 * убыванию id (новее — выше); при просмотре «всё» типы идут в порядке объявления источников.
 */
final class TagPageService
{
    public function __construct(
        private readonly TagReadRepository $tags,
        private readonly TaggableRegistry $registry,
        private readonly TagSettings $settings,
    ) {
    }

    /**
     * @param string|null $type фильтр по виду контента; неизвестный ключ трактуется как «все»
     */
    public function build(Tag $tag, ?string $type, int $page): TagPage
    {
        if ($type !== null && $this->registry->source($type) === null) {
            $type = null;
        }

        // Видимые id по каждому объявленному типу — в порядке объявления источников.
        $byType = $this->tags->entityIdsByTag($tag);
        $visibleByType = [];
        foreach ($this->registry->sources() as $sourceType => $source) {
            $ids = $byType[$sourceType] ?? [];
            if ($ids === []) {
                continue;
            }
            $visible = array_fill_keys($this->registry->visibleIds($sourceType, $ids), true);
            // Порядок сохраняем исходный (по убыванию id), провайдер его не гарантирует.
            $ordered = array_values(array_filter($ids, static fn (int $id): bool => isset($visible[$id])));
            if ($ordered !== []) {
                $visibleByType[$sourceType] = $ordered;
            }
        }

        $facets = [];
        foreach ($visibleByType as $sourceType => $ids) {
            $source = $this->registry->source($sourceType);
            $facets[] = new TagFacet($sourceType, $source->label, $source->icon, count($ids), $type === $sourceType);
        }

        $selected = $type === null ? $visibleByType : array_intersect_key($visibleByType, [$type => true]);

        // Плоский список пар (тип, id) → срез страницы → карточки пачкой на каждый тип среза.
        $flat = [];
        foreach ($selected as $sourceType => $ids) {
            foreach ($ids as $id) {
                $flat[] = [$sourceType, $id];
            }
        }
        $total = count($flat);
        $perPage = $this->settings->perPage;
        $page = max(1, min($page, max(1, (int)ceil($total / $perPage))));
        $slice = array_slice($flat, ($page - 1) * $perPage, $perPage);

        return new TagPage($tag, $facets, $this->hydrate($slice), $total, $page, $perPage, $type);
    }

    /**
     * @param array<int, array{0: string, 1: int}> $slice пары (тип, id) в нужном порядке
     * @return TaggedItem[] в порядке $slice; записи, которых провайдер не вернул, пропущены
     */
    private function hydrate(array $slice): array
    {
        $idsByType = [];
        foreach ($slice as [$type, $id]) {
            $idsByType[$type][] = $id;
        }

        $items = [];
        foreach ($idsByType as $type => $ids) {
            $provider = $this->registry->provider($type);
            if ($provider === null) {
                continue;
            }
            foreach ($provider->taggedItems($type, $ids) as $item) {
                $items[$type . ':' . $item->entityId] = $item;
            }
        }

        $ordered = [];
        foreach ($slice as [$type, $id]) {
            if (isset($items[$type . ':' . $id])) {
                $ordered[] = $items[$type . ':' . $id];
            }
        }
        return $ordered;
    }
}
