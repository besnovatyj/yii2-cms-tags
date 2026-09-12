<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\controllers\frontend;

use Besnovatyj\Tags\readModels\TagReadRepository;
use Besnovatyj\Tags\services\PopularTagsService;
use Besnovatyj\Tags\services\TagPageService;
use Yii;
use yii\data\Pagination;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Страницы тегов на сайте: `/tags` — облако, `/tag/<slug>` — записи всех модулей с тегом.
 *
 * Контроллер тонкий: разбирает запрос, отдаёт сервису, рендерит готовый результат. Что видно
 * посетителю, решают провайдеры контентных модулей — здесь визибилити не проверяется.
 */
class TagController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly TagReadRepository $tags,
        private readonly TagPageService $pages,
        private readonly PopularTagsService $popular,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $this->view->title = 'Теги';

        return $this->render('index', [
            'tags' => $this->popular->popular(),
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionView(string $slug): string
    {
        $tag = $this->tags->findBySlug($slug);
        if ($tag === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $request = Yii::$app->request;
        $type = $request->get('type');
        $type = is_string($type) && $type !== '' ? $type : null;
        $page = max(1, (int)$request->get('page', 1));

        $result = $this->pages->build($tag, $type, $page);

        // Тег без единой видимой записи для посетителя не существует — как и скрытая запись.
        if ($result->isEmpty()) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        // Pagination строится по факту: итог известен только после фильтрации провайдерами.
        $pagination = new Pagination([
            'totalCount' => $result->total,
            'pageSize' => $result->perPage,
            'defaultPageSize' => $result->perPage,
            'forcePageParam' => false,
        ]);

        $this->view->title = 'Тег: ' . $tag->name;

        // Страницы фильтра и пагинации — дубли; в индекс отдаём только каноническую страницу тега.
        if ($type !== null || $page > 1) {
            $this->view->registerMetaTag(['name' => 'robots', 'content' => 'noindex, follow'], 'robots');
        }

        return $this->render('view', [
            'result' => $result,
            'pagination' => $pagination,
        ]);
    }
}
