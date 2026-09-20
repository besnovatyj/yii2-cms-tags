<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\widgets\dashboard;

use Besnovatyj\Tags\results\TagStats;
use Besnovatyj\Tags\services\manage\TagManageService;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

/**
 * Плитка дашборда: состояние общего словаря тегов и его обслуживание.
 *
 * Словарь ничем не «собирается», поэтому даты сборки у него нет — его состояние описывают
 * отношения чисел. Засорение видно не по размеру словаря, а по доле пустых тегов: тег без единой
 * записи никуда не ведёт, но продолжает подсказываться при вводе, и через полгода правки записей
 * подсказка состоит наполовину из опечаток. «Ничьи» связи — след удалённого или выключенного
 * модуля; выключенный вернёт свои связи вместе с собой, поэтому их удаление остаётся отдельным
 * осознанным действием, а не частью уборки.
 *
 * Обе операции бьются AJAX-POST'ом в штатные эндпойнты модуля (те же, что у кнопок на странице
 * словаря) и возвращают пересчитанную сводку, которую плитка подставляет на месте. Кнопка видна
 * только тогда, когда есть что чистить: на здоровом словаре плитка — просто три числа.
 * JS — самодостаточный инлайн (fetch) в POS_END: без внешних ассетов и без зависимости от jQuery,
 * что согласуется с тем, что ассеты в админке подключает пользователь сам.
 *
 * Сервис берётся из контейнера прямо в `run()`: плитка инстанцируется дашбордом как обычный
 * виджет, без передачи зависимостей, и это единственное место, где его можно получить.
 */
class TagsTile extends Widget
{
    public function run(): string
    {
        $stats = Yii::$container->get(TagManageService::class)->stats();

        if ($stats->tags === 0) {
            return Html::tag(
                'div',
                'Словарь пуст: теги заводятся при сохранении записей, отдельной кнопки «создать тег» нет.',
                ['class' => 'text-muted small mb-2'],
            ) . $this->actions();
        }

        $this->registerActionsJs();

        return Html::tag(
            'div',
            $this->counters($stats) . $this->empty($stats) . $this->orphans($stats) . $this->actions(),
            ['id' => $this->rootId()],
        );
    }

    /**
     * Размер словаря и его наполненность. Число разделов рядом со связями отвечает на вопрос
     * «а точно ли теги используются везде, где я их завёл»: один раздел при семи контентных
     * модулях означает, что остальные к словарю так и не подключили.
     */
    private function counters(TagStats $stats): string
    {
        $big = Html::tag('span', (string)$stats->tags, [
            'class' => 'display-6 fw-bold lh-1',
            'data-tags-count' => true,
        ]);
        $caption = Html::tag('span', 'тегов в словаре', ['class' => 'text-muted ms-2']);

        $usage = Html::tag(
            'div',
            'Связей: ' . Html::tag('span', (string)$stats->assignments, ['data-tags-assignments' => true])
            . ' в ' . $stats->sources . ' ' . $this->plural($stats->sources, 'разделе', 'разделах', 'разделах'),
            ['class' => 'text-muted small mt-1'],
        );

        return Html::tag('div', $big . $caption, ['class' => 'd-flex align-items-baseline']) . $usage;
    }

    /**
     * Пустые теги: предупреждение с кнопкой уборки.
     *
     * Блок рендерится всегда, даже на пустом наборе, и скрывается атрибутом `hidden`: после
     * удаления «ничьих» связей часть тегов становится пустыми, и строке нужно появиться без
     * перезагрузки страницы.
     */
    private function empty(TagStats $stats): string
    {
        return $this->maintenanceRow(
            marker: 'empty',
            label: 'Пустых тегов:',
            count: $stats->empty,
            buttonLabel: 'Удалить пустые',
            buttonClass: 'btn btn-sm btn-outline-danger',
            url: Url::to(['/Tags/backend/tag/delete-empty']),
            confirm: 'Удалить все теги без записей?',
        );
    }

    /**
     * «Ничьи» связи: предупреждение с кнопкой уборки. Текст подтверждения тот же, что на странице
     * словаря, — операция одна и та же, и объяснять её двумя разными способами нельзя.
     */
    private function orphans(TagStats $stats): string
    {
        return $this->maintenanceRow(
            marker: 'orphans',
            label: '«Ничьих» связей:',
            count: $stats->orphans,
            buttonLabel: 'Почистить связи',
            buttonClass: 'btn btn-sm btn-outline-danger',
            url: Url::to(['/Tags/backend/tag/prune-orphans']),
            confirm: 'Связи будут удалены безвозвратно. Если модуль просто выключен — включите его вместо этого.',
        );
    }

    private function maintenanceRow(
        string $marker,
        string $label,
        int $count,
        string $buttonLabel,
        string $buttonClass,
        string $url,
        string $confirm,
    ): string {
        $text = Html::tag(
            'span',
            '<i class="bi bi-exclamation-triangle me-1"></i>' . $label . ' '
            . Html::tag('span', (string)$count, ['data-tags-' . $marker . '-count' => true]),
            ['class' => 'text-warning small'],
        );

        // Атрибуты намеренно свои, а не `data-url`/`data-confirm`: последние перехватывает
        // клиентский скрипт Yii (`yii.js`) и показывает собственное подтверждение поверх нашего.
        $button = Html::button($buttonLabel, [
            'type' => 'button',
            'class' => $buttonClass,
            'data-tags-action' => true,
            'data-tags-url' => $url,
            'data-tags-confirm' => $confirm,
        ]);

        return Html::tag('div', $text . $button, [
            'class' => 'd-flex align-items-center justify-content-between gap-2 mt-2',
            'data-tags-' . $marker => true,
            'hidden' => $count === 0,
        ]);
    }

    /**
     * Ссылка на словарь: правка тега, его записи и список пустых живут там, в плитке им тесно.
     */
    private function actions(): string
    {
        $link = Html::a(
            '<i class="bi bi-tags me-1"></i>К тегам',
            Url::to(['/Tags/backend/tag/index']),
            ['class' => 'btn btn-sm btn-outline-primary'],
        );

        $status = Html::tag('span', '', ['data-tags-status' => true, 'class' => 'text-muted small']);

        return Html::tag('div', $link . $status, ['class' => 'd-flex align-items-center gap-2 flex-wrap mt-3']);
    }

    private function rootId(): string
    {
        return 'dash-tags-' . $this->getId();
    }

    /**
     * Обработчик обеих кнопок: подтверждение → POST → подстановка пересчитанной сводки.
     *
     * Подтверждение спрашивается в самой плитке: обе операции удаляют данные безвозвратно, а на
     * главной панели кнопку легко нажать мимоходом. Кнопки на время запроса блокируются обе —
     * уборки пересекаются по данным, и параллельный второй запрос считал бы уже неверную сводку.
     *
     * Ошибку берём из тела ответа: эндпойнты отдают нативный JSON-конверт ошибки Yii
     * (`{name, message, ...}`) с реальным HTTP-статусом.
     */
    private function registerActionsJs(): void
    {
        $rootId = $this->rootId();
        $csrfHeader = Json::encode(Yii::$app->request->csrfHeader);
        $csrfToken = Json::encode(Yii::$app->request->getCsrfToken());

        $this->view->registerJs(
            <<<JS
            (function () {
                var root = document.getElementById('{$rootId}');
                if (!root) { return; }
                var status = root.querySelector('[data-tags-status]');
                var buttons = root.querySelectorAll('[data-tags-action]');

                function setNumber(selector, value) {
                    var el = root.querySelector(selector);
                    if (el) { el.textContent = value; }
                }

                function setRow(marker, value) {
                    var row = root.querySelector('[data-tags-' + marker + ']');
                    if (row) { row.hidden = value === 0; }
                    setNumber('[data-tags-' + marker + '-count]', value);
                }

                function apply(stats) {
                    setNumber('[data-tags-count]', stats.tags);
                    setNumber('[data-tags-assignments]', stats.assignments);
                    setRow('empty', stats.empty);
                    setRow('orphans', stats.orphans);
                }

                function disable(state) {
                    buttons.forEach(function (b) { b.disabled = state; });
                }

                buttons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!window.confirm(btn.dataset.tagsConfirm)) { return; }
                        disable(true);
                        status.textContent = 'Убираю…';
                        var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
                        headers[{$csrfHeader}] = {$csrfToken};
                        fetch(btn.dataset.tagsUrl, { method: 'POST', headers: headers })
                            .then(function (r) {
                                return r.json().then(function (body) {
                                    return r.ok ? body : Promise.reject(body);
                                });
                            })
                            .then(function (body) {
                                apply(body.stats);
                                status.textContent = body.message;
                            })
                            .catch(function (body) {
                                status.textContent = (body && body.message) || 'Ошибка операции';
                            })
                            .finally(function () { disable(false); });
                    });
                });
            })();
            JS,
            View::POS_END,
        );
    }

    /** Русское склонение числительного: 1 разделе, 2 разделах, 5 разделах. */
    private function plural(int $count, string $one, string $few, string $many): string
    {
        $mod100 = $count % 100;
        $mod10 = $count % 10;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return $many;
        }

        return match (true) {
            $mod10 === 1 => $one,
            $mod10 >= 2 && $mod10 <= 4 => $few,
            default => $many,
        };
    }
}
