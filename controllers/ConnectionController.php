<?php

namespace app\controllers;

use Yii;
use app\models\DbConnection;
use app\models\SyncLog;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

class ConnectionController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [ // Require login for all actions
                'class' => \yii\filters\AccessControl::class,
                'rules' => [[
                    'allow' => true,
                    'roles' => ['@'],
                ]],
            ],
            'verbs' => [ // Restrict HTTP methods for actions
                'class'   => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'test'   => ['POST'],
                ],
            ],
        ];
    }

    // GET /connection — list all registered connections
    public function actionIndex()
    {
        $connections = DbConnection::find()->orderBy(['type' => SORT_ASC, 'label' => SORT_ASC])->all();
        return $this->render('index', compact('connections'));
    }

    // GET /connection/create
    public function actionCreate()
    {
        $model = new DbConnection();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->save()) {
                SyncLog::write(null, 'info', "Connection registered: {$model->label}");
                Yii::$app->session->setFlash('success', "Connection <strong>{$model->label}</strong> registered successfully.");
                return $this->redirect(['index']);
            }
        }

        return $this->render('form', ['model' => $model, 'isNew' => true]);
    }

    // GET /connection/update?id=X
    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->save()) {
                SyncLog::write(null, 'info', "Connection updated: {$model->label}");
                Yii::$app->session->setFlash('success', "Connection <strong>{$model->label}</strong> updated.");
                return $this->redirect(['index']);
            }
        }

        // Don't expose encrypted password in the form — use blank password_plain
        $model->password_plain = '';
        return $this->render('form', ['model' => $model, 'isNew' => false]);
    }

    // POST /connection/delete?id=X
    public function actionDelete(int $id)
    {
        $model = $this->findModel($id);

        // Prevent deletion if used in any sync pair
        $usedInPairs = \app\models\SyncPair::find()
            ->where(['local_conn_id' => $id])
            ->orWhere(['cloud_conn_id' => $id])
            ->count();

        if ($usedInPairs > 0) {
            Yii::$app->session->setFlash('danger', "Cannot delete <strong>{$model->label}</strong> — it is used in {$usedInPairs} sync pair(s). Remove those pairs first.");
        } else {
            $label = $model->label;
            $model->delete();
            SyncLog::write(null, 'warning', "Connection deleted: {$label}");
            Yii::$app->session->setFlash('success', "Connection <strong>{$label}</strong> deleted.");
        }

        return $this->redirect(['index']);
    }

    // POST /connection/test?id=X  — AJAX or redirect
    public function actionTest(int $id)
    {
        $model  = $this->findModel($id);
        $result = $model->testConnection();

        $level   = $result['success'] ? 'success' : 'error';
        $message = "Connection test [{$model->label}]: {$result['message']}";
        SyncLog::write(null, $level, $message);

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return $result;
        }

        $flash = $result['success'] ? 'success' : 'danger';
        Yii::$app->session->setFlash($flash, $result['message']);
        return $this->redirect(['index']);
    }

    private function findModel(int $id): DbConnection
    {
        $model = DbConnection::findOne($id);
        if (!$model) throw new NotFoundHttpException("Connection #{$id} not found.");
        return $model;
    }
}