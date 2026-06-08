<?php

namespace app\controllers;

use Yii;
use app\models\SyncPair;
use app\models\DbConnection;
use app\models\SyncLog;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

class SyncPairController extends Controller
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
                    'toggle' => ['POST'],
                ],
            ],
        ];
    }

    // GET /sync-pair — list all pairs (used by dashboard)
    public function actionIndex()
    {
        $pairs = SyncPair::find()
            ->with(['localConn', 'cloudConn'])
            ->orderBy(['status' => SORT_ASC, 'created_at' => SORT_DESC])
            ->all();
        return $this->render('index', compact('pairs'));
    }

    // GET /sync-pair/create
    public function actionCreate()
    {
        $model       = new SyncPair();
        $localConns  = DbConnection::find()->where(['type' => 'local'])->all();
        $cloudConns  = DbConnection::find()->where(['type' => 'cloud'])->all();

        if (empty($localConns) || empty($cloudConns)) {
            Yii::$app->session->setFlash('warning', 'You need at least one <strong>local</strong> and one <strong>cloud</strong> connection before creating a pair. <a href="/connection/create">Register a connection →</a>');
            return $this->redirect(['index']);
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $model->status = 'active';
            if ($model->save()) {
                SyncLog::write($model->id, 'info', "Sync pair created: {$model->localConn->label} → {$model->cloudConn->label}");
                Yii::$app->session->setFlash('success', "Sync pair created successfully.");
                return $this->redirect(['dashboard/index']);
            }
        }

        return $this->render('form', compact('model', 'localConns', 'cloudConns'));
    }

    // GET /sync-pair/update?id=X
    public function actionUpdate(int $id)
    {
        $model      = $this->findModel($id);
        $localConns = DbConnection::find()->where(['type' => 'local'])->all();
        $cloudConns = DbConnection::find()->where(['type' => 'cloud'])->all();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->save()) {
                Yii::$app->session->setFlash('success', "Sync pair updated.");
                return $this->redirect(['dashboard/index']);
            }
        }

        return $this->render('form', compact('model', 'localConns', 'cloudConns'));
    }

    // POST /sync-pair/delete?id=X
    public function actionDelete(int $id)
    {
        $model = $this->findModel($id);
        $model->delete();
        Yii::$app->session->setFlash('success', 'Sync pair removed.');
        return $this->redirect(['dashboard/index']);
    }

    // POST /sync-pair/toggle?id=X  — toggle active/paused
    public function actionToggle(int $id)
    {
        $model = $this->findModel($id);

        if ($model->status === 'active') {
            $model->status = 'paused';
            $msg = "Sync pair paused: {$model->localConn->label} → {$model->cloudConn->label}";
        } elseif (in_array($model->status, ['paused', 'error'])) {
            $model->status = 'active';
            $model->last_error = null;
            $msg = "Sync pair resumed: {$model->localConn->label} → {$model->cloudConn->label}";
        } else {
            $msg = null;
        }

        $model->save(false);
        if ($msg) SyncLog::write($model->id, 'info', $msg);

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['status' => $model->status, 'label' => $model->getStatusLabel()];
        }

        return $this->redirect(['dashboard/index']);
    }

    private function findModel(int $id): SyncPair
    {
        $model = SyncPair::find()->with(['localConn', 'cloudConn'])->where(['id' => $id])->one();
        if (!$model) throw new NotFoundHttpException("Sync pair #{$id} not found.");
        return $model;
    }
}