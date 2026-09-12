<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Contracts\tags\TagSource;
use Besnovatyj\Tags\entities\Tag;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\DetailView;

/**
 * Карточка тега и таблица «где используется»: по типам — подпись источника и id записей.
 * Ссылок на записи нет: их backend-роуты модуль тегов не знает, а провайдер отдаёт только
 * публичные карточки. Для админа достаточно счётчиков и id.
 *
 * @var View                     $this
 * @var Tag                      $tag
 * @var array<string, int[]>     $usage   тип → id записей
 * @var array<string, TagSource> $sources
 * @var string                   $frontendUrl
 */

$this->title = $tag->name;
$this->params['breadcrumbs'][] = ['label' => 'Теги', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<p>
    <?= Html::a('Редактировать', ['update', 'id' => $tag->id], ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Удалить', ['delete', 'id' => $tag->id], [
        'class' => 'btn btn-danger',
        'data' => ['confirm' => 'Тег будет снят со всех записей. Удалить?', 'method' => 'post'],
    ]) ?>
    <?= Html::a('Страница тега на сайте', $frontendUrl, ['class' => 'btn btn-outline-secondary', 'target' => '_blank']) ?>
</p>

<div class="card mb-3">
    <div class="card-header"><?= Html::encode($this->title) ?></div>
    <div class="card-body">
        <?= DetailView::widget([
            'model' => $tag,
            'attributes' => ['id', 'name', 'slug'],
        ]) ?>
    </div>
</div>

<div class="card">
    <div class="card-header">Где используется</div>
    <div class="card-body">
        <?php if ($usage === []): ?>
            <p class="text-muted mb-0">Ни одной записи.</p>
        <?php else: ?>
            <table class="table table-sm mb-0">
                <thead>
                <tr><th>Вид записей</th><th>Ключ</th><th>Записей</th><th>ID</th></tr>
                </thead>
                <tbody>
                <?php foreach ($usage as $type => $ids): ?>
                    <tr<?= isset($sources[$type]) ? '' : ' class="table-warning"' ?>>
                        <td><?= isset($sources[$type]) ? Html::encode($sources[$type]->label) : '<em>модуль не установлен</em>' ?></td>
                        <td><code><?= Html::encode($type) ?></code></td>
                        <td><?= count($ids) ?></td>
                        <td class="text-muted small"><?= implode(', ', $ids) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
