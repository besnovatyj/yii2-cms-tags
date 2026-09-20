<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Tags\controllers\backend;

use Besnovatyj\Kernel\controller\ControllerTrait;
use Besnovatyj\Kernel\urlmanager\UrlManagerHelperTrait;
use Besnovatyj\Tags\entities\Tag;
use Besnovatyj\Tags\entities\TagAssignment;
use Besnovatyj\Tags\forms\backend\search\TagSearch;
use Besnovatyj\Tags\forms\backend\TagForm;
use Besnovatyj\Tags\services\manage\TagManageService;
use Besnovatyj\Tags\services\TaggableRegistry;
use DomainException;
use Throwable;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Словарь тегов в админке: список, правка, удаление, чистка пустых и «ничьих», Select2-эндпоинт.
 *
 * Экшена `create` нет намеренно: тег создаётся при сохранении записи (см. TagsForm).
 */
class TagController extends Controller
{
    use ControllerTrait;
    use UrlManagerHelperTrait;

    public function __construct(
        $id,
        $module,
        private readonly TagManageService $service,
        private readonly TaggableRegistry $registry,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'delete-empty' => ['POST'],
                    'prune-orphans' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new TagSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'orphanTypes' => $this->service->orphanTypes(),
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): string
    {
        $tag = $this->findModel($id);

        // Где используется: по типам — подпись источника (или сырой ключ у «ничьих») и id записей.
        $usage = [];
        $rows = TagAssignment::find()
            ->select(['entity_type', 'entity_id'])
            ->andWhere(['tag_id' => $tag->id])
            ->orderBy(['entity_type' => SORT_ASC, 'entity_id' => SORT_DESC])
            ->asArray()
            ->all();
        foreach ($rows as $row) {
            $usage[$row['entity_type']][] = (int)$row['entity_id'];
        }

        return $this->render('view', [
            'tag' => $tag,
            'usage' => $usage,
            'sources' => $this->registry->sources(),
            'frontendUrl' => $this->getAbsoluteFrontendRoute('/Tags/tag/view', ['slug' => $tag->slug]),
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionUpdate(int $id): Response|string
    {
        $tag = $this->findModel($id);

        $form = new TagForm($tag);
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $this->service->edit($tag->id, $form);
                return $this->redirect(['view', 'id' => $tag->id]);
            } catch (DomainException $e) {
                $this->handleDomainException($e);
            }
        }

        return $this->render('update', [
            'model' => $form,
            'tag' => $tag,
        ]);
    }

    public function actionDelete(int $id): Response
    {
        try {
            $this->service->remove($id);
        } catch (Throwable $e) {
            $this->handleDomainException($e);
        }
        return $this->redirect(['index']);
    }

    public function actionEmpty(): string
    {
        return $this->render('empty', [
            'dataProvider' => $this->service->findEmpty(),
        ]);
    }

    public function actionDeleteEmpty(): Response|array
    {
        if (Yii::$app->request->getIsAjax()) {
            return $this->maintenanceAsJson(
                fn (): string => 'Удалено пустых тегов: ' . $this->service->deleteEmpty() . '.',
            );
        }

        try {
            $count = $this->service->deleteEmpty();
            Yii::$app->session->setFlash('success', "Удалено пустых тегов: {$count}.");
        } catch (Throwable $e) {
            $this->handleDomainException($e);
        }
        return $this->redirect(['empty']);
    }

    public function actionPruneOrphans(): Response|array
    {
        if (Yii::$app->request->getIsAjax()) {
            return $this->maintenanceAsJson(
                fn (): string => 'Удалено «ничьих» связей: ' . $this->service->pruneOrphans() . '.',
            );
        }

        try {
            $count = $this->service->pruneOrphans();
            Yii::$app->session->setFlash('success', "Удалено «ничьих» связей: {$count}.");
        } catch (Throwable $e) {
            $this->handleDomainException($e);
        }
        return $this->redirect(['index']);
    }

    /**
     * Та же уборка, но для плитки дашборда: ответ — пересчитанная сводка словаря, а не редирект.
     *
     * Сделано ответвлением существующих экшенов, а не отдельными: операции ровно те же, и
     * раздваивать их значило бы получить две точки, где однажды разойдутся права и поведение.
     * Различается только конверт ответа.
     *
     * Ошибки отдаются нативным JSON-конвертом Yii ({@see \yii\web\ErrorHandler}) с реальным
     * HTTP-статусом: формат выставлен до операции, поэтому и исключение уйдёт как JSON. Текст
     * исключения наружу не выносится (вне отладки) — так же, как это делает
     * {@see ControllerTrait::handleDomainException()} для обычных запросов.
     *
     * @param callable():string $operation уборка, возвращающая сообщение о результате
     *
     * @return array{message:string,stats:array{tags:int,assignments:int,empty:int,orphans:int}}
     *
     * @throws ServerErrorHttpException если уборка не удалась — причина уже в журнале.
     */
    private function maintenanceAsJson(callable $operation): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            $message = $operation();
        } catch (Throwable $e) {
            Yii::$app->errorHandler->logException($e);

            throw new ServerErrorHttpException(
                YII_DEBUG ? $e->getMessage() : 'Не удалось выполнить операцию.',
                0,
                $e,
            );
        }

        $stats = $this->service->stats();

        return [
            'message' => $message,
            'stats' => [
                'tags' => $stats->tags,
                'assignments' => $stats->assignments,
                'empty' => $stats->empty,
                'orphans' => $stats->orphans,
            ],
        ];
    }

    /**
     * Подсказки для Select2Widget: `{"results": [{"id": ..., "text": "имя"}]}`.
     *
     * Один эндпоинт на все модули — тот самый выигрыш общего словаря: тег, заведённый в галерее,
     * подсказывается в блоге.
     */
    public function actionSearchEndpoint(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $q = trim((string)Yii::$app->request->get('q', ''));

        $tags = Tag::find()
            ->select(['id', 'text' => 'name'])
            ->andFilterWhere(['like', 'name', $q])
            ->orderBy(['name' => SORT_ASC])
            ->limit(20)
            ->asArray()
            ->all();

        return ['results' => $tags];
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findModel(int $id): Tag
    {
        if (($model = Tag::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
