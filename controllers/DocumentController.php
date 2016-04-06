<?php
/**
 * @package   yii2-document
 * @author    Yuri Shekhovtsov <shekhovtsovy@yandex.ru>
 * @copyright Copyright &copy; Yuri Shekhovtsov, lowbase.ru, 2015 - 2016
 * @version   1.0.0
 */
 
namespace lowbase\document\controllers;

use Yii;
use lowbase\document\models\Document;
use lowbase\document\models\DocumentSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
//use yii\filters\AccessControl;


/**
 * DocumentController implements the CRUD actions for Document model.
 * Абсолютные пути Views использованы, чтобы при наследовании
 * происходила связь с отображениями модуля родителя.
 */
class DocumentController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
// Ограничение доступа к операциям, связанным с документами
//            'access' => [
//                'class' => AccessControl::className(),
//                'only' => ['index', 'view', 'create', 'update', 'delete', 'multidelete', 'multiactive', 'multiblock', 'move'],
//                'rules' => [
//                ],
//            ],
        ];
    }

    /**
     * Менеджер документов.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new DocumentSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('@vendor/lowbase/yii2-document/views/document/index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Просмотр документа.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('@vendor/lowbase/yii2-document/views/document/view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Создание документа.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Document();
        $model->parent_id = Yii::$app->request->get('parent_id');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->getSession()->setFlash('success', Yii::t('document', 'Новый документ создан.'));
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('@vendor/lowbase/yii2-document/views/document/create', [
            'model' => $model,
        ]);
    }

    /**
     * Редактирование документа.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->getSession()->setFlash('success', Yii::t('document', 'Документ отредактирован.'));
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('@vendor/lowbase/yii2-document/views/document/update', [
            'model' => $model,
        ]);
    }

    /**
     * Удаление документа.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        if (Yii::$app->request->isAjax) {
            return true;
        } else {
            Yii::$app->getSession()->setFlash('success', Yii::t('document', 'Документ удален.'));
            return $this->redirect(['index']);
        }
    }

    /**
     * Множественное удаление документов
     * @return bool
     * @throws NotFoundHttpException
     */
    public function actionMultidelete()
    {
        $models = Yii::$app->request->post('keys');
        if ($models) {
            foreach ($models as $id) {
                    $this->findModel($id)->delete();
            }
            Yii::$app->getSession()->setFlash('success', Yii::t('document', 'Документы удалены.'));
        }
        return true;
    }

    /**
     * Множественная публикация документов
     * @return bool
     * @throws NotFoundHttpException
     */
    public function actionMultiactive()
    {
        $models = Yii::$app->request->post('keys');
        if ($models) {
            foreach ($models as $id) {
                if ($id != Yii::$app->user->id) {
                    $model = $this->findModel($id);
                    $model->status = 1;
                    $model->save();
                }
            }
            Yii::$app->getSession()->setFlash('success', Yii::t('document', 'Документы опубликованы.'));
        }
        return true;
    }

    /**
     * Множественное снятие с публикации документов
     * @return bool
     * @throws NotFoundHttpException
     */
    public function actionMultiblock()
    {
        $models = Yii::$app->request->post('keys');
        if ($models) {
            foreach ($models as $id) {
                if ($id != Yii::$app->user->id) {
                    $model = $this->findModel($id);
                    $model->status = 0;
                    $model->save();
                }
            }
            Yii::$app->getSession()->setFlash('success', Yii::t('document', 'Документы сняты с публикации.'));
        }
        return true;
    }

    /**
     * Перемещение докуемнта
     * @return bool
     * @throws NotFoundHttpException
     */
     public function actionMove()
    {
        $data = Yii::$app->request->post();
        $model = $this->findModel($data['id']);
        $old_parent_id = $model->parent_id;
        $model->parent_id = ($data['new_parent_id'] == '#') ? null : $data['new_parent_id'];

        if ($data['new_prev_id'] && $data['new_prev_id'] !== 'false') {
            $prev_model = $this->findModel($data['new_prev_id']);
            $model->position = $prev_model->position+1;
        } else {
            $model->position = 0;
        }
        $db = $model->getDb();
        $transaction = $db->beginTransaction();
        try {
            $db->createCommand("set @i:=". $model->position)->execute();
            $db->createCommand('UPDATE lb_document SET position=(@i:=@i+1) WHERE (parent_id='.$model->parent_id.' && `position`>='.$model->position.') ORDER BY position')->execute();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
        $model->save();

        if ($old_parent_id <> $model->parent_id) {
            if ($old_parent_id !== '#') {
                Document::folder($old_parent_id);
            }
            if ($old_parent_id !== null) {
                Document::folder($model->parent_id);
            }
        }
        return true;
    }
     /**
     * @param integer $id
     * @return Document the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Document::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(Yii::t('document', 'Запрашиваемая страница не найдена.'));
        }
    }
}
