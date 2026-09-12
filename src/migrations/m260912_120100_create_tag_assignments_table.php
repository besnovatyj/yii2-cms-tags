<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\migrations;

use Besnovatyj\Kernel\migration\BaseMigration;
use yii\base\NotSupportedException;

/**
 * Полиморфная связь «тег ↔ запись любого модуля».
 *
 * Запись адресуется парой `entity_type` (ключ `<модуль>.<сущность>`, тот же, что в контрактах
 * поиска и карты сайта) + `entity_id`. Внешнего ключа на таблицу записи нет и быть не может —
 * таблиц много и они в разных модулях; целостность держится конвенцией: удаление записи обязано
 * идти через сервис модуля с вызовом `TagAssigner::detachAll()`, сироты после `deleteAll()` в обход
 * сервиса безвредны (провайдер их не найдёт) и вычищаются командой `tags/prune`.
 *
 * `entity_id` — целое, а не строка: по этой колонке идут связи AR контентных модулей
 * (`hasMany(TagAssignment)` с целым `id`), и строковая колонка при сравнении с числом теряла бы
 * индекс. Сущности со строковым первичным ключом к тегам не привязываются — осознанно.
 */
class m260912_120100_create_tag_assignments_table extends BaseMigration
{
    public const string TABLE_NAME = '{{%tag_assignments}}';

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
            'tag_id'      => $this->integer()->notNull()
                ->comment('Тег'),
            'entity_type' => $this->string(64)->notNull()
                ->comment('Ключ вида записи: <модуль>.<сущность>, напр. blog.post'),
            'entity_id'   => $this->integer()->notNull()
                ->comment('Первичный ключ записи в таблице её модуля'),
        ], $this->tableOptions);
        $this->addCommentOnTable(static::TABLE_NAME, 'Связь тегов с записями модулей');

        // Первичный ключ — вся тройка: одна запись получает тег не более одного раза.
        $this->createIndexes(static::TABLE_NAME, ['tag_id', 'entity_type', 'entity_id'], true);
        // Обратный проход «запись → её теги» (формы, карточки в списках).
        $this->createIndexes(static::TABLE_NAME, ['entity_type', 'entity_id']);

        $this->createFKs(static::TABLE_NAME, 'tag_id', m260912_120000_create_tags_table::TABLE_NAME, 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * @throws NotSupportedException
     */
    public function safeDown(): void
    {
        parent::safeDown();
    }
}
