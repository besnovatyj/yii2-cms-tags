<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\results;

/**
 * Вкладка страницы тега: вид контента и сколько видимых записей с этим тегом в нём.
 */
final readonly class TagFacet
{
    public function __construct(
        public string $type,
        public string $label,
        public ?string $icon,
        public int $count,
        public bool $active,
    ) {
    }
}
