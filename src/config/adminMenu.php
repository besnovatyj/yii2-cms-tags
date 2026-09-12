<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

return [
    // Словарь тегов
    [
        'label'     => 'Теги',
        'iconClass' => 'bi bi-tags me-1',
        'url'       => ['/Tags/backend/tag/index'],
        'active'    => static function () {
            return str_contains(\Yii::$app->request->url, 'Tags/backend/tag');
        },
        '_meta' => [
            'placements' => [
                [
                    'location'      => 'left-sidebar',
                    'group'         => 'Tags',
                    'groupIcon'     => 'bi bi-tags',
                    'priority'      => 100,
                    'groupPriority' => 650,
                ],
            ],
        ],
    ],
];
