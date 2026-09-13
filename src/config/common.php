<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Tags\Module;
use Besnovatyj\Tags\readModels\TagReadRepository;
use Besnovatyj\Tags\services\PopularTagsService;
use Besnovatyj\Tags\services\TaggableRegistry;
use Besnovatyj\Tags\settings\TagSettings;
use Besnovatyj\Validators\SlugValidator;
use yii\di\Container;

/**
 * Yii2-конфиг модуля для движка yiisoft/config (группа `common` — общий для всех приложений).
 *
 * Единственный composition root пакета: регистрация модуля, URL-правила страниц тегов и DI-проводка.
 * Файла `config/container.php` нет намеренно: он выполняется только при инициализации модуля, а
 * сервисы тегов вызывают из чужих модулей (форма поста, сервис галереи, облако в теме) — проводка
 * обязана существовать независимо от того, зашёл ли запрос в модуль Tags.
 *
 * URL-правила — вклад в `frontendUrlManager` (компонент есть и во фронте, и в бэкенде, поэтому
 * группа `common`): `/tags` — облако, `/tag/<slug>` — страница тега (фильтр по типу и пагинация
 * через query: `?type=blog.post&page=2`). Слаг тега — {@see SlugValidator::SLUG_ANY}: тег живёт в своём
 * сегменте и с числовым `<id>` не конкурирует, поэтому может начинаться с цифры.
 */
return [
    'modules' => [
        Module::moduleId() => array_merge(
            ['class' => Module::class],
            Module::moduleConfig(),
            ['version' => Module::moduleVersion()],
        ),
    ],
    'components' => [
        'frontendUrlManager' => [
            'rules' => [
                'tags'                                       => 'Tags/tag/index',
                'tag/<slug:' . SlugValidator::SLUG_ANY . '>' => 'Tags/tag/view',
            ],
        ],
    ],
    'container' => [
        'singletons' => [
            TagSettings::class => static fn (): TagSettings => TagSettings::fromParams(
                (array)(Yii::$app->getModule(Module::MODULE_ID)?->params ?? []),
            ),

            // Реестр провайдеров обходит модули приложения — один на запрос.
            TaggableRegistry::class => TaggableRegistry::class,

            // Кэш — компонент приложения, в контейнере по интерфейсу не зарегистрирован; резолвим здесь.
            PopularTagsService::class => static fn (Container $c): PopularTagsService => new PopularTagsService(
                $c->get(TagReadRepository::class),
                $c->get(TaggableRegistry::class),
                $c->get(TagSettings::class),
                Yii::$app->cache,
            ),
        ],
    ],
];
