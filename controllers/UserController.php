<?php

namespace app\controllers;

use Yii;
use app\models\User;
use app\models\ApiToken;
use app\models\SyncLog;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;

class UserController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [[
                    'allow' => true,
                    'roles' => ['@'],   // logged in only
                ]],
            ],
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'delete'           => ['POST'],
                    'toggle-status'    => ['POST'],
                    'generate-token'   => ['POST'],
                    'revoke-token'     => ['POST'],
                ],
            ],
        ];
    }

    // ----------------------------------------------------------------
    // GET /user  — list all users (admin only)
    // ----------------------------------------------------------------
    public function actionIndex()
    {
        $this->requireAdmin();
        $users = User::find()->orderBy(['role' => SORT_ASC, 'username' => SORT_ASC])->all();
        return $this->render('index', compact('users'));
    }

    // ----------------------------------------------------------------
    // GET /user/create  (admin only)
    // ----------------------------------------------------------------
    public function actionCreate()
    {
        $this->requireAdmin();
        $model = new User();
        $model->scenario = 'create';

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->save()) {
                SyncLog::write(null, 'info', "User created: {$model->username} (role: {$model->role})");
                Yii::$app->session->setFlash('success', "User <strong>{$model->username}</strong> created.");
                return $this->redirect(['index']);
            }
        }

        return $this->render('form', ['model' => $model, 'isNew' => true]);
    }

    // ----------------------------------------------------------------
    // GET /user/update?id=X
    // ----------------------------------------------------------------
    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        // Operators can only edit themselves
        if (!Yii::$app->user->identity->isAdmin() && Yii::$app->user->id != $id) {
            throw new ForbiddenHttpException('You can only edit your own profile.');
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->save()) {
                Yii::$app->session->setFlash('success', "Profile updated.");
                return $this->redirect(['index']);
            }
        }

        $model->password = '';
        return $this->render('form', ['model' => $model, 'isNew' => false]);
    }

    // ----------------------------------------------------------------
    // POST /user/delete?id=X  (admin only)
    // ----------------------------------------------------------------
    public function actionDelete(int $id)
    {
        $this->requireAdmin();

        if (Yii::$app->user->id == $id) {
            Yii::$app->session->setFlash('danger', 'You cannot delete your own account.');
            return $this->redirect(['index']);
        }

        $model = $this->findModel($id);
        $username = $model->username;
        $model->delete();
        SyncLog::write(null, 'warning', "User deleted: {$username}");
        Yii::$app->session->setFlash('success', "User <strong>{$username}</strong> deleted.");
        return $this->redirect(['index']);
    }

    // ----------------------------------------------------------------
    // POST /user/toggle-status?id=X  (admin only)
    // ----------------------------------------------------------------
    public function actionToggleStatus(int $id)
    {
        $this->requireAdmin();
        $model = $this->findModel($id);

        if (Yii::$app->user->id == $id) {
            Yii::$app->session->setFlash('danger', 'You cannot deactivate your own account.');
            return $this->redirect(['index']);
        }

        $model->status = $model->status === User::STATUS_ACTIVE
            ? User::STATUS_INACTIVE
            : User::STATUS_ACTIVE;
        $model->save(false);

        $label = $model->status === User::STATUS_ACTIVE ? 'activated' : 'deactivated';
        SyncLog::write(null, 'info', "User {$label}: {$model->username}");
        Yii::$app->session->setFlash('success', "User <strong>{$model->username}</strong> {$label}.");
        return $this->redirect(['index']);
    }

    // ----------------------------------------------------------------
    // POST /user/generate-token?id=X
    // ----------------------------------------------------------------
    public function actionGenerateToken(int $id)
    {
        $model = $this->findModel($id);

        // Only admin or the user themselves
        if (!Yii::$app->user->identity->isAdmin() && Yii::$app->user->id != $id) {
            throw new ForbiddenHttpException();
        }

        $label = Yii::$app->request->post('label', 'Default');
        $token = ApiToken::generate($model->id, $label);
        SyncLog::write(null, 'info', "API token generated for user: {$model->username}");

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['success' => true, 'token' => $token->token, 'id' => $token->id];
        }

        Yii::$app->session->setFlash('success', "API token generated. Copy it now — it won't be shown again.");
        Yii::$app->session->set('new_token', $token->token);
        return $this->redirect(['update', 'id' => $id]);
    }

    // ----------------------------------------------------------------
    // POST /user/revoke-token?id=X
    // ----------------------------------------------------------------
    public function actionRevokeToken(int $id)
    {
        $token = ApiToken::findOne($id);
        if ($token) {
            $token->delete();
            SyncLog::write(null, 'info', "API token revoked (id: {$id})");
        }

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['success' => true];
        }

        Yii::$app->session->setFlash('success', 'Token revoked.');
        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function requireAdmin(): void
    {
        if (!Yii::$app->user->identity->isAdmin()) {
            throw new ForbiddenHttpException('Admin access required.');
        }
    }

    private function findModel(int $id): User
    {
        $model = User::findOne($id);
        if (!$model) throw new NotFoundHttpException("User #{$id} not found.");
        return $model;
    }
}