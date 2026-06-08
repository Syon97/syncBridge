<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Users';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <a href="<?= Url::to(['/user/create']) ?>" class="btn btn-sm btn-success">
        <i class="bi bi-person-plus me-1"></i>Add user
    </a>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title">All users</span>
    </div>
    <div class="panel-body">
        <?php foreach ($users as $user): ?>
            <div class="pair-row">
                <div style="flex:1;">
                    <div class="d-flex align-items-center gap-2">
                        <!-- Avatar initials -->
                        <div style="width:34px;height:34px;border-radius:50%;background:<?= $user->isAdmin() ? '#1D9E75' : '#378ADD' ?>;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;color:#fff;flex-shrink:0;">
                            <?= strtoupper(substr($user->username, 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-size:13.5px;font-weight:500;color:var(--color-text-primary, #1a1a2e);">
                                <?= Html::encode($user->username) ?>
                                <?php if ($user->id === Yii::$app->user->id): ?>
                                    <span class="badge text-bg-secondary ms-1" style="font-size:9px;">YOU</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:#9BA3B2;"><?= Html::encode($user->email) ?></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <span class="badge text-bg-<?= $user->getRoleBadgeClass() ?>" style="font-size:10px;">
                        <?= strtoupper($user->role) ?>
                    </span>
                    <span class="badge <?= $user->status === \app\models\User::STATUS_ACTIVE ? 'text-bg-success' : 'text-bg-secondary' ?>" style="font-size:10px;">
                        <?= $user->getStatusLabel() ?>
                    </span>
                    <span style="font-size:11px;color:#9BA3B2;">
                        <?= $user->last_login_at ? 'Last: ' . Yii::$app->formatter->asRelativeTime($user->last_login_at) : 'Never logged in' ?>
                    </span>

                    <!-- Edit -->
                    <a href="<?= Url::to(['/user/update', 'id' => $user->id]) ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                    </a>

                    <?php if ($user->id !== Yii::$app->user->id): ?>
                        <!-- Toggle status -->
                        <form method="post" action="<?= Url::to(['/user/toggle-status', 'id' => $user->id]) ?>">
                            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $user->status ? 'Deactivate' : 'Activate' ?>">
                                <i class="bi bi-<?= $user->status ? 'pause' : 'play' ?>-circle"></i>
                            </button>
                        </form>

                        <!-- Delete -->
                        <form method="post" action="<?= Url::to(['/user/delete', 'id' => $user->id]) ?>"
                              onsubmit="return confirm('Delete user \'<?= Html::encode($user->username) ?>\'? This cannot be undone.')">
                            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>