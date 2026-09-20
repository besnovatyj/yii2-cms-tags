<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\results;

/**
 * Сводка по словарю тегов — то, что показывается администратору одним взглядом (плитка дашборда).
 *
 * Отдельным объектом, а не массивом: числа связаны между собой и читаются только вместе. «300
 * тегов» само по себе ничего не говорит; «300 тегов, из них 180 пустых» означает, что словарь
 * засорён черновиками и подсказка при вводе перестала помогать.
 */
final readonly class TagStats
{
    /**
     * @param int $tags        всего тегов в словаре
     * @param int $assignments всего связей «тег ↔ запись»
     * @param int $empty       теги без единой связи — кандидаты на удаление
     * @param int $orphans     связи с видами записей, которых не объявляет ни один включённый модуль
     * @param int $sources     сколько видов контента объявлено включёнными модулями
     */
    public function __construct(
        public int $tags,
        public int $assignments,
        public int $empty,
        public int $orphans,
        public int $sources,
    ) {
    }

    /** Есть ли что обслуживать: пустые теги или «ничьи» связи. */
    public function needsAttention(): bool
    {
        return $this->empty > 0 || $this->orphans > 0;
    }
}
