<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Tags\results\TagCount;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/**
 * Базовая вью страницы `/tags`: облако популярных тегов. Тема переопределяет через overlay.
 *
 * @var View       $this
 * @var TagCount[] $tags по убыванию счётчика
 */
?>
<div class="container py-4">
    <h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

    <?php if ($tags === []): ?>
        <p class="text-muted">Тегов пока нет.</p>
    <?php else: ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($tags as $item): ?>
                <?= Html::a(
                    Html::encode($item->tag->name) . ' <span class="badge text-bg-light">' . $item->count . '</span>',
                    Url::to(['/Tags/tag/view', 'slug' => $item->tag->slug]),
                    ['class' => 'btn btn-outline-secondary btn-sm'],
                ) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
