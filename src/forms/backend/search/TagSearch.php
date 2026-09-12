<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\forms\backend\search;

use Besnovatyj\Forms\BaseForm;
use Besnovatyj\Tags\entities\Tag;
use Besnovatyj\Tags\entities\TagAssignment;
use yii\data\ActiveDataProvider;

/**
 * Фильтр списка тегов в админке. Колонка `usage_count` — число связей по всем модулям (коррелированный
 * подзапрос, сортируемая). Строки отдаются массивами: у сущности {@see Tag} нет свойства `usage`,
 * а заводить его ради грида незачем — GridView и ActionColumn с массивами работают штатно.
 */
class TagSearch extends BaseForm
{
    public ?int $id = null;
    public string $name = '';
    public string $slug = '';

    public function rules(): array
    {
        return [
            [['id'], 'integer'],
            [['name', 'slug'], 'string'],
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $usage = TagAssignment::find()
            ->select('COUNT(*)')
            ->andWhere('[[tags_assignments_usage]].[[tag_id]] = [[t]].[[id]]')
            ->from(['tags_assignments_usage' => TagAssignment::tableName()]);

        $query = Tag::find()->alias('t')
            ->select(['t.*', 'usage_count' => $usage])
            ->asArray();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'attributes' => ['id', 'name', 'slug', 'usage_count'],
                'defaultOrder' => ['name' => SORT_ASC],
            ],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $query
            ->andFilterWhere(['t.id' => $this->id])
            ->andFilterWhere(['like', 't.name', $this->name])
            ->andFilterWhere(['like', 't.slug', $this->slug]);

        return $dataProvider;
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'slug' => 'Slug',
        ];
    }
}
