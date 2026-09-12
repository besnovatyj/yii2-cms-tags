<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\services;

use Besnovatyj\Tags\entities\Tag;
use Besnovatyj\Tags\entities\TagAssignment;
use Besnovatyj\Tags\forms\backend\TagForm;
use InvalidArgumentException;
use Throwable;
use Yii;
use yii\caching\TagDependency;
use yii\db\Query;

/**
 * Перенос тегов из legacy-таблиц контентного модуля (`<module>_tags` + `<module>_tag_asgmt`)
 * в общий словарь.
 *
 * О модулях ничего не знает — работает по именам таблиц и колонок, которые передаёт оператор.
 * Слияние по slug: одинаковый slug из разных модулей становится одним тегом (имя — от первого
 * встреченного). Slug legacy-тега перевыводится из имени через {@see TagForm::slugFor()}, если
 * старый не проходит нынешнюю конвенцию (первый символ — буква), — иначе страница тега была бы
 * недостижима по URL-правилу. Повторный запуск безопасен: существующие связи пропускаются.
 */
final class LegacyTagImporter
{
    /**
     * @param callable(string): void|null $log
     * @return array{read: int, created: int, merged: int, assigned: int, skipped: int}
     * @throws Throwable
     */
    public function import(
        string $tagsTable,
        string $assignmentsTable,
        string $fkColumn,
        string $type,
        bool $dryRun = false,
        ?callable $log = null,
    ): array {
        $log ??= static function (string $message): void {};
        // Соединение — то же, что у AR тегов: legacy-таблицы модулей живут в той же базе.
        $db = Tag::getDb();

        foreach ([$tagsTable, $assignmentsTable, $fkColumn] as $identifier) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
                throw new InvalidArgumentException("Недопустимый идентификатор: {$identifier}");
            }
        }
        if (!preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $type)) {
            throw new InvalidArgumentException("Ключ типа должен быть вида module.entity: {$type}");
        }

        $tagsTable = '{{%' . $tagsTable . '}}';
        $assignmentsTable = '{{%' . $assignmentsTable . '}}';
        foreach ([$tagsTable, $assignmentsTable] as $table) {
            if ($db->getTableSchema($table, true) === null) {
                throw new InvalidArgumentException("Таблица не найдена: {$table}");
            }
        }

        $report = ['read' => 0, 'created' => 0, 'merged' => 0, 'assigned' => 0, 'skipped' => 0];

        $transaction = $db->beginTransaction();
        try {
            // 1. Теги: legacy id → id в общем словаре.
            $map = [];
            $legacyTags = (new Query())->select(['id', 'name', 'slug'])->from($tagsTable)->orderBy('id')->all($db);
            foreach ($legacyTags as $row) {
                $report['read']++;
                $name = trim((string)$row['name']);
                $slug = (string)$row['slug'];
                if (!preg_match('/^[a-z][a-z0-9_-]*$/', $slug)) {
                    $fixed = TagForm::slugFor($name);
                    $log("  slug «{$slug}» не проходит конвенцию → «{$fixed}»");
                    $slug = $fixed;
                }

                $existing = Tag::findOne(['slug' => $slug]);
                if ($existing !== null) {
                    $map[(int)$row['id']] = (int)$existing->id;
                    $report['merged']++;
                    continue;
                }

                $report['created']++;
                if ($dryRun) {
                    $map[(int)$row['id']] = 0;
                    continue;
                }
                $tag = Tag::create($name, $slug);
                if (!$tag->save()) {
                    throw new \RuntimeException("Не удалось сохранить тег «{$name}»: " . implode(' ', $tag->getFirstErrors()));
                }
                $map[(int)$row['id']] = (int)$tag->id;
            }
            $log("  тегов: {$report['read']} (новых {$report['created']}, слитых {$report['merged']})");

            // 2. Связи: (fk, tag_id) → (type, entity_id, new tag_id); дубли пропускаем.
            $legacy = (new Query())
                ->select([$fkColumn, 'tag_id'])
                ->from($assignmentsTable)
                ->orderBy([$fkColumn => SORT_ASC]);
            foreach ($legacy->batch(500, $db) as $rows) {
                $batch = [];
                foreach ($rows as $row) {
                    $tagId = $map[(int)$row['tag_id']] ?? null;
                    if ($tagId === null) {
                        $report['skipped']++;
                        continue;
                    }
                    $batch[] = [$tagId, $type, (int)$row[$fkColumn]];
                }
                if ($batch === []) {
                    continue;
                }
                $report['assigned'] += count($batch);
                if ($dryRun) {
                    continue;
                }
                // INSERT IGNORE: первичный ключ по тройке отсеивает уже существующие связи.
                $sql = $db->createCommand()
                    ->batchInsert(TagAssignment::tableName(), ['tag_id', 'entity_type', 'entity_id'], $batch)
                    ->getRawSql();
                $db->createCommand(preg_replace('/^INSERT/i', 'INSERT IGNORE', $sql))->execute();
            }
            $log("  связей: {$report['assigned']} (пропущено {$report['skipped']})");

            if ($dryRun) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
                if (Yii::$app->has('cache')) {
                    TagDependency::invalidate(Yii::$app->cache, [TagAssigner::CACHE_TAG]);
                }
            }
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $report;
    }
}
