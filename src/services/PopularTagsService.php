<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\services;

use Besnovatyj\Tags\readModels\TagReadRepository;
use Besnovatyj\Tags\results\TagCount;
use Besnovatyj\Tags\settings\TagSettings;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;

/**
 * Облако тегов: самые частые теги, считанные ТОЛЬКО по видимым записям.
 *
 * Тег, все записи которого скрыты, в облако не попадает — иначе посетитель уходит по нему на
 * пустую страницу и узнаёт о скрытом контенте. Видимость знает лишь провайдер, поэтому отбор
 * двухфазный: дешёвый `GROUP BY` даёт кандидатов с запасом, провайдеры фильтруют их записи, итог
 * сортируется по видимому счётчику. Результат кэшируется: сброс — при любом изменении связей
 * (тег кэша {@see TagAssigner::CACHE_TAG}) и по TTL `countsTtl` — на случай, когда запись скрыли
 * или опубликовали, а теги при этом не трогали.
 */
final class PopularTagsService
{
    /** Во сколько раз больше кандидатов брать, чем нужно в облаке: часть отсеется по видимости. */
    private const int CANDIDATES_FACTOR = 3;

    public function __construct(
        private readonly TagReadRepository $tags,
        private readonly TaggableRegistry $registry,
        private readonly TagSettings $settings,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param int|null    $limit сколько тегов; null — из настроек
     * @param string|null $type  только по одному виду контента; null — по всем
     * @return TagCount[] по убыванию счётчика
     */
    public function popular(?int $limit = null, ?string $type = null): array
    {
        $limit ??= $this->settings->cloudLimit;

        /** @var array<int, int> $counts tag_id → видимый счётчик */
        $counts = $this->cache->getOrSet(
            ['tags.popular', 'limit' => $limit, 'type' => $type],
            fn (): array => $this->countVisible($limit, $type),
            $this->settings->countsTtl > 0 ? $this->settings->countsTtl : null,
            new TagDependency(['tags' => [TagAssigner::CACHE_TAG]]),
        );

        $tags = $this->tags->findByIds(array_keys($counts));
        $result = [];
        foreach ($counts as $tagId => $count) {
            if (isset($tags[$tagId])) {
                $result[] = new TagCount($tags[$tagId], $count);
            }
        }
        return $result;
    }

    /**
     * @return array<int, int> tag_id → счётчик видимых записей, по убыванию, не больше $limit
     */
    private function countVisible(int $limit, ?string $type): array
    {
        $candidates = $this->tags->topTagIdsByRawCount($limit * self::CANDIDATES_FACTOR);
        $byType = $this->tags->assignmentsOfTags($candidates);

        $counts = [];
        foreach ($byType as $entityType => $byTag) {
            if ($type !== null && $entityType !== $type) {
                continue;
            }
            // Одним вызовом на тип: id всех кандидатов вместе, потом раскладываем обратно по тегам.
            $all = array_unique(array_merge(...array_values($byTag)));
            $visible = array_fill_keys($this->registry->visibleIds($entityType, $all), true);
            foreach ($byTag as $tagId => $ids) {
                foreach ($ids as $id) {
                    if (isset($visible[$id])) {
                        $counts[$tagId] = ($counts[$tagId] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($counts, SORT_NUMERIC);
        return array_slice($counts, 0, $limit, true);
    }
}
