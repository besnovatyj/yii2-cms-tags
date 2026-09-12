<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags;

use Besnovatyj\Contracts\module\DeclaresModule;
use Besnovatyj\Contracts\module\ProvidesAdminMenu;
use Besnovatyj\Contracts\module\ProvidesDependencies;
use Besnovatyj\Contracts\module\ProvidesMigrations;
use Besnovatyj\Contracts\module\ProvidesOptions;
use Besnovatyj\Kernel\module\CmsModule;

/**
 * Модуль общих тегов.
 *
 * Один словарь тегов и одна полиморфная таблица связей на все контентные модули — вместо семи
 * копий сущностей, форм, контроллёров и таблиц. Теги — плоская сквозная тема («о чём»), в отличие
 * от категорий/таксономий — дерева, своего у каждого модуля («где лежит»). Отдельных словарей на
 * модуль нет намеренно: нужен раздельный словарь — это категория.
 *
 * Сервисный модуль, а не опциональный: контентные модули зависят от него явно (`require` в
 * composer.json), используют его {@see forms\backend\TagsForm} в своих композитных формах и
 * {@see services\TagAssigner} в сервисах. Обратной зависимости нет — что показать на странице тега и
 * что из этого видно анониму, модуль тегов узнаёт через контракт
 * {@see \Besnovatyj\Contracts\tags\TaggableProvider}, реализованный классом контентного модуля.
 */
class Module extends CmsModule implements
    DeclaresModule,
    ProvidesAdminMenu,
    ProvidesDependencies,
    ProvidesMigrations,
    ProvidesOptions
{
    public const bool EDITABLE = true;
    public const string VERSION = '1.0.0';
    public const string MODULE_ID = 'Tags';

    public static function moduleId(): string { return self::MODULE_ID; }
    public static function moduleVersion(): string { return self::VERSION; }
    public static function isEditable(): bool { return self::EDITABLE; }
    public static function adminMenu(): array { return require __DIR__ . '/config/adminMenu.php'; }
    public static function moduleConfig(): array { return require __DIR__ . '/config/config.php'; }
    public static function options(): array { return require __DIR__ . '/config/options.php'; }
    public static function dependencies(): array { return require __DIR__ . '/config/dependencies.php'; }
    public static function migrationPath(): string { return __DIR__ . '/migrations'; }
    public static function migrationNamespace(): ?string { return __NAMESPACE__ . '\\migrations'; }
}
