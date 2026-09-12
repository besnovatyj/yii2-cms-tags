<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\settings;

/**
 * Настройки модуля тегов — типизированный снимок `params` модуля.
 *
 * Собирается один раз за запрос в composition root (config/common.php): значения модуля настроек
 * `yii2-cms-config` применяются к объекту модуля после подъёма приложения, поэтому читать их можно
 * только лениво. Сервисы получают этот объект, а не `Yii::$app->getModule(...)`.
 */
final readonly class TagSettings
{
    public function __construct(
        public int $perPage = 20,
        public int $cloudLimit = 30,
        public int $countsTtl = 600,
    ) {
    }

    /**
     * @param array<string, mixed> $params `params` модуля
     */
    public static function fromParams(array $params): self
    {
        return new self(
            perPage: max(1, (int)($params['perPage'] ?? 20)),
            cloudLimit: max(1, (int)($params['cloudLimit'] ?? 30)),
            countsTtl: max(0, (int)($params['countsTtl'] ?? 600)),
        );
    }
}
