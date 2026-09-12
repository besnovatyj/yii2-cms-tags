<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\results;

use Besnovatyj\Tags\entities\Tag;

/**
 * Тег со счётчиком видимых записей — элемент облака.
 */
final readonly class TagCount
{
    public function __construct(
        public Tag $tag,
        public int $count,
    ) {
    }
}
