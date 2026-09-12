<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Contracts\tags\TaggedItem;
use Besnovatyj\Tags\results\TagPage;
use yii\bootstrap5\LinkPager;
use yii\data\Pagination;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/**
 * Базовая вью страницы тега (пакетный фолбэк; тема переопределяет через overlay
 * `modules/Tags/views/frontend/tag/view.php`). Разметка — по образцу выдачи поиска.
 *
 * @var View       $this
 * @var TagPage    $result
 * @var Pagination $pagination
 */

$link = static function (?string $type) use ($result): array {
    return array_filter(['/Tags/tag/view', 'slug' => $result->tag->slug, 'type' => $type]);
};

$labels = [];
foreach ($result->facets as $facet) {
    $labels[$facet->type] = $facet->label;
}
?>
<div class="container py-4">
    <h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

    <?php if (count($result->facets) > 1): ?>
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <?= Html::a(
                    'Всё <span class="badge text-bg-secondary">' . $result->total . '</span>',
                    $link(null),
                    ['class' => 'nav-link' . ($result->type === null ? ' active' : '')],
                ) ?>
            </li>
            <?php foreach ($result->facets as $facet): ?>
                <li class="nav-item">
                    <?= Html::a(
                        ($facet->icon !== null ? '<i class="' . Html::encode($facet->icon) . ' me-1"></i>' : '')
                        . Html::encode($facet->label)
                        . ' <span class="badge text-bg-secondary">' . $facet->count . '</span>',
                        $link($facet->type),
                        ['class' => 'nav-link' . ($facet->active ? ' active' : '')],
                    ) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="list-group list-group-flush mb-4">
        <?php foreach ($result->items as $item): ?>
            <?php /** @var TaggedItem $item */ ?>
            <?php $url = Url::to(array_merge([$item->route], $item->params)); ?>
            <article class="list-group-item px-0 py-3">
                <div class="d-flex gap-3">
                    <?php if ($item->image !== null): ?>
                        <div class="flex-shrink-0 d-none d-sm-block">
                            <?= Html::a(
                                Html::img($item->image, [
                                    'alt' => $item->title,
                                    'class' => 'rounded',
                                    'style' => 'width:96px;height:96px;object-fit:cover;',
                                    'loading' => 'lazy',
                                ]),
                                $url,
                            ) ?>
                        </div>
                    <?php endif; ?>

                    <div class="flex-grow-1">
                        <h2 class="h6 mb-1"><?= Html::a(Html::encode($item->title), $url) ?></h2>
                        <div class="small text-muted mb-1">
                            <?= Html::encode($labels[$item->type] ?? $item->type) ?>
                            <?php if ($item->date !== null): ?>
                                · <?= Yii::$app->formatter->asDate($item->date) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($item->excerpt !== null && $item->excerpt !== ''): ?>
                            <p class="mb-0"><?= Html::encode($item->excerpt) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?= LinkPager::widget(['pagination' => $pagination]) ?>
</div>
