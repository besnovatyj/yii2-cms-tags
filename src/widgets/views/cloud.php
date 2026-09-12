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
 * @var View       $this
 * @var TagCount[] $tags
 * @var bool       $showCount
 */
?>
<div class="tag-cloud d-flex flex-wrap gap-2">
    <?php foreach ($tags as $item): ?>
        <?= Html::a(
            Html::encode($item->tag->name)
            . ($showCount ? ' <span class="badge text-bg-light">' . $item->count . '</span>' : ''),
            Url::to(['/Tags/tag/view', 'slug' => $item->tag->slug]),
            ['class' => 'btn btn-outline-secondary btn-sm', 'rel' => 'tag'],
        ) ?>
    <?php endforeach; ?>
</div>
