<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\services;

use Besnovatyj\Contracts\tags\TaggableProvider;
use Besnovatyj\Contracts\tags\TagSource;
use Besnovatyj\Kernel\module\ModuleFinder;
use Yii;

/**
 * Реестр провайдеров тегов: находит модули, реализующие {@see TaggableProvider}, и сводит их
 * источники в одну карту «тип → источник / провайдер».
 *
 * Обход зарегистрированных модулей по контракту ({@see ModuleFinder}) — тот же приём, что у поиска
 * и меню: модуль тегов не знает имён контентных модулей, а инстанцируются только провайдеры.
 * Отключённый в modman модуль в конфиг не попадает,
 * поэтому его типы исчезают из фасетов сами, а его связи в таблице становятся «ничьими» и просто
 * не показываются.
 */
final class TaggableRegistry
{
    /** @var array<string, TagSource>|null тип → источник */
    private ?array $sources = null;

    /** @var array<string, TaggableProvider>|null тип → провайдер */
    private ?array $providers = null;

    /**
     * Все объявленные источники. Ключ — тип.
     *
     * @return array<string, TagSource>
     */
    public function sources(): array
    {
        $this->collect();
        return $this->sources;
    }

    public function source(string $type): ?TagSource
    {
        return $this->sources()[$type] ?? null;
    }

    public function provider(string $type): ?TaggableProvider
    {
        $this->collect();
        return $this->providers[$type] ?? null;
    }

    /**
     * Из переданных id записей типа — видимые анониму. Неизвестный тип → ничего не видно.
     *
     * Провайдер получает id пачками: список у популярного тега может быть длинным, а `IN (...)`
     * на тысячи значений — плохой запрос.
     *
     * @param int[] $ids
     * @return int[]
     */
    public function visibleIds(string $type, array $ids): array
    {
        $provider = $this->provider($type);
        if ($provider === null || $ids === []) {
            return [];
        }

        $visible = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($provider->visibleTaggedIds($type, $chunk) as $id) {
                $visible[] = (int)$id;
            }
        }
        return $visible;
    }

    private function collect(): void
    {
        if ($this->sources !== null) {
            return;
        }

        $this->sources = [];
        $this->providers = [];

        foreach (ModuleFinder::implementing(TaggableProvider::class) as $module) {
            foreach ($module->tagSources() as $source) {
                if (isset($this->sources[$source->type])) {
                    Yii::warning("Источник тегов «{$source->type}» объявлен дважды; взят первый.", 'tags/registry');
                    continue;
                }
                $this->sources[$source->type] = $source;
                $this->providers[$source->type] = $module;
            }
        }
    }
}
