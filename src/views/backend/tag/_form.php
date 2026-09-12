<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Tags\forms\backend\TagForm;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var View    $this
 * @var TagForm $model
 */
?>
<?php $form = ActiveForm::begin(); ?>
<div class="card">
    <div class="card-header"><?= Html::encode($this->title) ?></div>
    <div class="card-body">
        <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
        <?= $form->field($model, 'slug')->textInput(['maxlength' => true])
            ->hint('Пусто — выводится из названия. Только a-z, 0-9, «-», «_»; первый символ — буква. Смена slug меняет адрес страницы тега.') ?>
    </div>
    <div class="card-footer clearfix">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>
</div>
<?php ActiveForm::end(); ?>
