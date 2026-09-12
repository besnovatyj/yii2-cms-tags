<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\commands;

use Besnovatyj\Tags\services\LegacyTagImporter;
use Besnovatyj\Tags\services\manage\TagManageService;
use Throwable;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Консоль модуля тегов.
 *
 *  - `php yii Tags/tag/import --tags=blog_tags --assignments=blog_tag_asgmt --fk=post_id --type=blog.post`
 *    — перенос тегов из legacy-таблиц контентного модуля в общий словарь (по разу на модуль);
 *  - `php yii Tags/tag/prune` — удалить связи «ничьих» типов (модуль удалён).
 *
 * На проде — от пользователя веб-сервера (`sudo -u www-data php yii ...`), иначе процесс не
 * прочитает секреты подключения к базе.
 */
final class TagController extends Controller
{
    /** Legacy-таблица тегов (имя без префикса и скобок, напр. `blog_tags`). */
    public string $tags = '';

    /** Legacy-таблица связей (напр. `blog_tag_asgmt`). */
    public string $assignments = '';

    /** Колонка записи в таблице связей (напр. `post_id`). */
    public string $fk = '';

    /** Ключ вида записи в общей таблице связей (напр. `blog.post`). */
    public string $type = '';

    /** Только показать, что будет сделано. */
    public bool $dryRun = false;

    public function __construct(
        $id,
        $module,
        private readonly LegacyTagImporter $importer,
        private readonly TagManageService $service,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function options($actionID): array
    {
        return match ($actionID) {
            'import' => ['tags', 'assignments', 'fk', 'type', 'dryRun'],
            default => [],
        };
    }

    public function optionAliases(): array
    {
        return ['n' => 'dryRun'];
    }

    /**
     * Перенести теги и связи одного модуля из его legacy-таблиц в общий словарь.
     */
    public function actionImport(): int
    {
        foreach (['tags', 'assignments', 'fk', 'type'] as $option) {
            if ($this->$option === '') {
                $this->stderr("Не задан --{$option}\n", Console::FG_RED);
                return ExitCode::USAGE;
            }
        }

        $this->stdout(
            "Импорт тегов: {$this->tags} + {$this->assignments}.{$this->fk} → {$this->type}"
            . ($this->dryRun ? ' (только проверка)' : '') . "\n",
            Console::BOLD,
        );

        try {
            $report = $this->importer->import(
                $this->tags,
                $this->assignments,
                $this->fk,
                $this->type,
                $this->dryRun,
                function (string $message): void {
                    $this->stdout($message . "\n");
                },
            );
        } catch (Throwable $e) {
            $this->stderr('Ошибка: ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNAVAILABLE;
        }

        $this->stdout(sprintf(
            "Готово: тегов прочитано %d (новых %d, слиты с существующими по slug %d), связей записано %d, пропущено дублей %d.\n",
            $report['read'],
            $report['created'],
            $report['merged'],
            $report['assigned'],
            $report['skipped'],
        ), Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Удалить связи с типами, которых не объявляет ни один установленный модуль.
     */
    public function actionPrune(): int
    {
        $orphans = $this->service->orphanTypes();
        if ($orphans === []) {
            $this->stdout("«Ничьих» связей нет.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        foreach ($orphans as $type => $count) {
            $this->stdout("  {$type}: {$count}\n");
        }
        if (!$this->confirm('Удалить эти связи? Если модуль просто выключен — включите его вместо этого.')) {
            return ExitCode::OK;
        }

        $count = $this->service->pruneOrphans();
        $this->stdout("Удалено связей: {$count}.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
