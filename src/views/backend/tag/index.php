<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Backend\Widgets\grid\ActionColumn;
use Besnovatyj\Backend\Widgets\pagination\LinkPager;
use Besnovatyj\Kernel\security\AccessHelper;
use Besnovatyj\Tags\forms\backend\search\TagSearch;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;

/**
 * Список тегов общего словаря. Строки — массивы (см. TagSearch), поэтому `$model['name']`.
 *
 * @var View               $this
 * @var TagSearch          $searchModel
 * @var ActiveDataProvider $dataProvider
 * @var array<string, int> $orphanTypes тип → число «ничьих» связей
 */

$this->title = 'Теги';
$this->params['breadcrumbs'][] = $this->title;
?>
<p>
    <?= Html::a('Пустые теги', ['empty'], ['class' => 'btn btn-warning']) ?>
</p>

<?php if ($orphanTypes !== []): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <div>
            Связи с видами записей, которых не объявляет ни один установленный модуль:
            <?php foreach ($orphanTypes as $type => $count): ?>
                <code><?= Html::encode($type) ?></code> — <?= $count ?>;
            <?php endforeach; ?>
            модуль деактивирован или удалён.
        </div>
        <?= Html::a('Удалить «ничьи» связи', ['prune-orphans'], [
            'class' => 'btn btn-outline-danger btn-sm text-nowrap ms-3',
            'data' => ['confirm' => 'Связи будут удалены безвозвратно. Если модуль просто выключен — включите его вместо этого.', 'method' => 'post'],
        ]) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><?= Html::encode($this->title) ?></div>
    <div class="card-body">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'layout' => "{summary}\n{items}",
            'columns' => [
                'id',
                [
                    'attribute' => 'name',
                    'value' => static fn (array $model) => Html::a(Html::encode($model['name']), ['view', 'id' => $model['id']]),
                    'format' => 'raw',
                ],
                'slug',
                [
                    'attribute' => 'usage_count',
                    'label' => 'Записей',
                    'value' => static fn (array $model) => (int)$model['usage_count'],
                    'filter' => false,
                ],
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
