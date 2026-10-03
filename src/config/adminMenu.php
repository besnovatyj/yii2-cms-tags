<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Contracts\adminMenu\AdminMenuLocation;
use Besnovatyj\Contracts\adminMenu\AdminMenuPlacement;

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
                new AdminMenuPlacement(
                    location: AdminMenuLocation::LeftSidebar,
                    group: 'Tags',
                    groupIcon: 'bi bi-tags',
                    groupPriority: 650,
                    priority: 100,
                ),
            ],
        ],
    ],
];
