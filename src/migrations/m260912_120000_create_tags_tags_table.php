<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\migrations;

use Besnovatyj\Kernel\migration\BaseMigration;
use yii\base\NotSupportedException;

/**
 * Общий словарь тегов всех контентных модулей.
 *
 * `slug` уникален и служит адресом страницы тега `/tag/<slug>`; по нему же сводятся одинаковые теги
 * разных модулей при импорте (`tags/import`). Статуса у тега нет: тег «жив», пока на него есть хотя
 * бы одна видимая запись — это решает не колонка, а провайдеры (см. контракт TaggableProvider).
 */
class m260912_120000_create_tags_tags_table extends BaseMigration
{
    public const string TABLE_NAME = '{{%tags_tags}}';

    /**
     * @throws NotSupportedException
     */
    public function safeUp(): void
    {
        parent::safeUp();

        if ($this->existTable(static::TABLE_NAME)) {
            return;
        }

        $this->createTable(static::TABLE_NAME, [
            'id'   => $this->primaryKey(),
            'name' => $this->string(255)->notNull()
                ->comment('Название тега'),
            'slug' => $this->string(255)->notNull()
                ->comment('Slug тега — адрес страницы /tag/<slug>'),
        ], $this->tableOptions);
        $this->addCommentOnTable(static::TABLE_NAME, 'Общий словарь тегов');

        // Уникален только slug. Имя проверяет форма (без учёта регистра): DB-уникальность по имени в
        // ci-коллации считала бы «Ёлка» и «Елка» дубликатами, и импорт legacy-таблиц падал бы на них.
        $this->createIndexes(static::TABLE_NAME, 'slug', false, true);
    }

    /**
     * @throws NotSupportedException
     */
    public function safeDown(): void
    {
        parent::safeDown();
    }
}
