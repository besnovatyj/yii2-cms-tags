<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Backend\Widgets\grid\ActionColumn;
use Besnovatyj\Backend\Widgets\pagination\LinkPager;
use Besnovatyj\Kernel\security\AccessHelper;
use Besnovatyj\Tags\entities\Tag;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;

/**
 * Теги без единой связи.
 *
 * @var View               $this
 * @var ActiveDataProvider $dataProvider
 */

$this->title = 'Пустые теги';
$this->params['breadcrumbs'][] = ['label' => 'Теги', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<p>
    <?= Html::a('Удалить все пустые теги', ['delete-empty'], [
        'class' => 'btn btn-danger',
        'data' => ['confirm' => 'Удалить все теги без записей?', 'method' => 'post'],
    ]) ?>
</p>
<div class="card">
    <div class="card-header"><?= Html::encode($this->title) ?></div>
    <div class="card-body">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'layout' => "{summary}\n{items}",
            'columns' => [
                'id',
                [
                    'attribute' => 'name',
                    'value' => static fn (Tag $model) => Html::a(Html::encode($model->name), ['view', 'id' => $model->id]),
                    'format' => 'raw',
                ],
                'slug',
                [
                    'class' => ActionColumn::class,
                    'template' => AccessHelper::filterActionColumn(['view', 'update', 'delete']),
                ],
            ],
        ]) ?>
    </div>
    <div class="card-footer clearfix">
        <nav aria-label="" class="nav-pagination">
            <?= LinkPager::widget(['pagination' => $dataProvider->getPagination()]) ?>
        </nav>
    </div>
</div>
