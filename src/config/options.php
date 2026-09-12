<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

/**
 * Опции модуля настроек `yii2-cms-config` для модуля тегов. Пути указывают в `modules.Tags.params.*`.
 */
return [
    'tags_per_page' => [
        'path'        => 'modules.Tags.params.perPage',
        'label'       => '[Tags] Записей на странице тега',
        'category'    => 'Tags',
        'rules'       => [
            ['required'],
            ['integer', 'min' => 1, 'max' => 200],
        ],
        'inputOptions' => ['type' => 'text'],
    ],

    'tags_cloud_limit' => [
        'path'        => 'modules.Tags.params.cloudLimit',
        'label'       => '[Tags] Тегов в облаке',
        'category'    => 'Tags',
        'rules'       => [
            ['required'],
            ['integer', 'min' => 1, 'max' => 500],
        ],
        'inputOptions' => ['type' => 'text'],
    ],

    'tags_counts_ttl' => [
        'path'        => 'modules.Tags.params.countsTtl',
        'label'       => '[Tags] Кэш счётчиков облака, секунд',
        'description' => 'Через сколько облако заметит скрытую или опубликованную запись',
        'category'    => 'Tags',
        'rules'       => [
            ['required'],
            ['integer', 'min' => 0, 'max' => 86400],
        ],
        'inputOptions' => ['type' => 'text'],
    ],
];
