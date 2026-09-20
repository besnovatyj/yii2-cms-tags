# yii2-cms-tags — общие теги

Один словарь тегов и одна таблица связей на все контентные модули. Теги — плоская сквозная
тема («о чём»), категории и таксономии — дерево, своё у каждого модуля («где лежит»). Отдельных
словарей на модуль нет намеренно: нужен раздельный словарь — это категория.

## Что даёт

- таблицы `tags_tags` и `tags_assignments(tag_id, entity_type, entity_id)`; ключ типа `entity_type` —
  тот же `<модуль>.<сущность>` (`blog.post`), что в контрактах поиска, карты сайта и меню;
- `forms\backend\TagsForm` — вложенная форма назначения тегов для композитной формы модуля;
  `forms\backend\TagForm` — форма одного тега (валидация, вывод slug);
- `services\TagAssigner` — единственная точка записи связей (`sync`, `detachAll`);
- `readModels\TagReadRepository` — теги записи / пачки записей, id записей по тегу;
- админка `/Tags/backend/tag/*` — список, правка, удаление, пустые теги, «ничьи» связи,
  Select2-эндпоинт `search-endpoint` (один на все модули);
- фронт `/tag/<slug>` (записи всех модулей с фасетами по типу, `?type=&page=`) и `/tags` (облако);
  `widgets\TagCloud` для тем;
- консоль `Tags/tag/import` (перенос legacy-таблиц модуля) и `Tags/tag/prune`.

Создания тега в админке нет: тег рождается при сохранении записи. В админке — только правка
опечатки и удаление.

## Подключение контентного модуля

### 1. `composer.json`

```json
"require": { "besnovatyj/yii2-cms-tags": "^1.0" }
```

### 2. Сущность — связи через трейт

```php
use Besnovatyj\Tags\entities\TaggableEntityTrait;

class Post extends ActiveRecord
{
    use TaggableEntityTrait;

    public static function tagType(): string { return 'blog.post'; }
}
// дальше обычный AR: Post::find()->with('tags'), $post->tags
```

### 3. Форма записи — вложенная `TagsForm`

```php
use Besnovatyj\Tags\forms\backend\TagsForm;

class PostForm extends CompositeForm
{
    public function __construct(?Post $post = null, $config = [])
    {
        $this->tags = new TagsForm($post?->tags ?? []);
        // ...
    }

    protected function internalForms(): array { return ['meta', 'tags']; }
}
```

Вью формы:

```php
<?= $form->field($model->tags, 'newTagsNames')->widget(Select2Widget::class, [
    'endpoint' => Url::to(['/Tags/backend/tag/search-endpoint'], true),
]) ?>
```

### 4. Сервис записи — `TagAssigner`

```php
public function __construct(private readonly TagAssigner $tags, ...) {}

// create / edit — внутри своей транзакции, после сохранения записи:
$this->tags->sync(Post::tagType(), $post->id, $form->tags->items);

// remove — ДО или после удаления записи, обязательно:
$this->tags->detachAll(Post::tagType(), $post->id);
```

`$form->tags->items` — это `TagForm[]`, заполненные валидацией: для каждого имени либо форма
существующего тега, либо провалидированная новая. Slug модуль не выводит сам.

### 5. Класс модуля — `TaggableProvider`

```php
use Besnovatyj\Contracts\tags\TaggableProvider;
use Besnovatyj\Contracts\tags\TagSource;
use Besnovatyj\Contracts\tags\TaggedItem;

class Module extends CmsModule implements ..., TaggableProvider
{
    public function tagSources(): array
    {
        return [new TagSource('blog.post', 'Статьи блога', 'bi bi-newspaper')];
    }

    public function visibleTaggedIds(string $type, array $ids): array
    {
        return match ($type) {
            'blog.post' => (new PostReadRepository())->visibleIds($ids), // Post::find()->visible()->andWhere(['id' => $ids])->column()
            default => [],
        };
    }

    public function taggedItems(string $type, array $ids): iterable
    {
        return match ($type) {
            'blog.post' => (new PostReadRepository())->taggedItems($ids), // yield new TaggedItem(...)
            default => [],
        };
    }
}
```

Оба метода обязаны отдавать только то, что анонимный посетитель может открыть: страница тега
общая и публичная.

### 6. Страница тега модуля (по желанию)

Свою `blog/tag/<slug>` модуль может оставить: `TagReadRepository::entityIdsByTag($tag, 'blog.post')`
→ свой readModel → свои карточки в стиле раздела. Облако только по своим записям —
`TagCloud::widget(['type' => 'blog.post'])`.

## Перенос данных из legacy-таблиц

По разу на модуль, после установки модуля Tags и до удаления старых таблиц:

```
php yii Tags/tag/import --tags=blog_tags --assignments=blog_tag_asgmt --fk=post_id --type=blog.post --dryRun
php yii Tags/tag/import --tags=blog_tags --assignments=blog_tag_asgmt --fk=post_id --type=blog.post
```

Слияние по slug: одинаковые slug разных модулей становятся одним тегом. Slug, не проходящий
конвенцию (первый символ — буква), перевыводится из имени. Повторный запуск безопасен.

## Целостность

Внешнего ключа `tags_assignments → запись` нет и быть не может (таблицы записей — в разных
модулях). Целостность — конвенцией: удаление записи идёт через сервис модуля с `detachAll()`.
Связи, оставшиеся после `deleteAll()` в обход сервиса, безвредны: провайдер их не найдёт, на
страницу они не попадут. Связи типов, которых не объявляет ни один установленный модуль,
показываются в админке и удаляются кнопкой или `Tags/tag/prune`.

## Плитка панели админки

Модуль объявляет плитку для модуля `yii2-cms-dashboard` (`Tags.dictionary`): размер словаря,
число связей и разделов, а также строки уборки — «Пустых тегов: N» и «„Ничьих“ связей: N» с
кнопками. Строка и кнопка появляются, только когда есть что чистить. Кнопки бьют AJAX-POST'ом в
те же эндпойнты `/Tags/backend/tag/delete-empty` и `/Tags/backend/tag/prune-orphans`: на AJAX они
отвечают JSON'ом с пересчитанной сводкой (плитка подставляет числа на месте), на обычный POST —
как и раньше, редиректом с флеш-сообщением.

## Видимость и кэш

Модуль тегов не знает, какие записи видимы, — это отдаёт провайдер. Счётчики облака и `/tags`
считаются по видимым записям и кэшируются: сброс — при любом изменении связей (тег кэша
`tags`), по времени — `countsTtl` (по умолчанию 10 мин; столько облако может не замечать, что
запись скрыли или опубликовали). Страница тега счётчики не кэширует — они считаются на запрос.

## Настройки (`yii2-cms-config`)

`perPage` — записей на странице тега; `cloudLimit` — тегов в облаке; `countsTtl` — кэш
счётчиков, секунд.
