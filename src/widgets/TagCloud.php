<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\widgets;

use Besnovatyj\Tags\services\PopularTagsService;
use yii\base\Widget;

/**
 * Облако популярных тегов — для сайдбаров и подвалов тем.
 *
 * `type` ограничивает облако одним видом контента (`'blog.post'` — только теги статей блога):
 * так модуль может показать «своё» облако на своих страницах, оставаясь на общем словаре.
 *
 * ```php
 * <?= TagCloud::widget(['limit' => 20, 'type' => 'blog.post']) ?>
 * ```
 */
class TagCloud extends Widget
{
    /** Сколько тегов; null — из настроек модуля. */
    public ?int $limit = null;

    /** Только по одному виду контента; null — по всем. */
    public ?string $type = null;

    /** Показывать счётчик у тега. */
    public bool $showCount = true;

    public function __construct(
        private readonly PopularTagsService $popular,
        $config = [],
    ) {
        parent::__construct($config);
    }

    public function run(): string
    {
        $tags = $this->popular->popular($this->limit, $this->type);
        if ($tags === []) {
            return '';
        }

        return $this->render('cloud', [
            'tags' => $tags,
            'showCount' => $this->showCount,
        ]);
    }
}
