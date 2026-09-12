<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\results;

use Besnovatyj\Contracts\tags\TaggedItem;
use Besnovatyj\Tags\entities\Tag;

/**
 * Готовая страница тега: вкладки по видам контента, карточки текущей страницы, итог.
 *
 * Вью ничего не досчитывает — как у поисковой выдачи.
 */
final readonly class TagPage
{
    /**
     * @param TagFacet[]   $facets только виды с хотя бы одной видимой записью
     * @param TaggedItem[] $items  карточки текущей страницы
     * @param string|null  $type   выбранный фильтр по виду; null — все
     */
    public function __construct(
        public Tag $tag,
        public array $facets,
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public ?string $type,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }
}
