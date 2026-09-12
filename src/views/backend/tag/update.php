<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Tags\entities\Tag;
use Besnovatyj\Tags\forms\backend\TagForm;
use yii\web\View;

/**
 * @var View    $this
 * @var Tag     $tag
 * @var TagForm $model
 */

$this->title = 'Тег: ' . $tag->name;
$this->params['breadcrumbs'][] = ['label' => 'Теги', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $tag->name, 'url' => ['view', 'id' => $tag->id]];
$this->params['breadcrumbs'][] = 'Правка';
?>
<div class="tag-update">
    <?= $this->render('_form', ['model' => $model]) ?>
</div>
