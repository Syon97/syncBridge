<?php
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = $isNew ? 'Add User' : 'Edit User';
$isOwnProfile = !$isNew && $model->id === Yii::$app->user->id;
$isAdmin = Yii::$app->user->identity->isAdmin();
$newToken = Yii::$app->session->getFlash('new_token') ?? null;
?>

<div class="row justify-content-center">
<div class="col-md-7">

<?php if ($newToken): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3" style="font-family:monospace;font-size:13px;">
        <i class="bi bi-key-fill"></i>
        <div>
            <strong>New API token — copy it now, it won't be shown again:</strong><br>
            <span id="token-val" style="word-break:break-all;"><?= Html::encode($newToken) ?></span>
        </div>
        <button class="btn btn-sm btn-outline-success ms-auto" onclick="copyToken()">
            <i class="bi bi-clipboard"></i>
        </button>
    </div>
<script>
function copyToken() {
    navigator.clipboard.writeText(document.getElementById('token-val').textContent);
    event.target.closest('button').innerHTML = '<i class="bi bi-check"></i>';
}
</script>
<?php endif; ?>

<!-- Profile form -->
<div class="panel mb-3">
    <div class="panel-header">
        <span class="panel-title"><?= $isNew ? 'New user' : 'Edit profile' ?></span>
    </div>
    <div class="p-4">
    <?php $form = ActiveForm::begin(); ?>

        <div class="row g-3 mb-3">
            <div class="col-6">
                <?= $form->field($model, 'username')->textInput(['placeholder' => 'username']) ?>
            </div>
            <div class="col-6">
                <?= $form->field($model, 'email')->textInput(['placeholder' => 'email@example.com']) ?>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6">
                <?= $form->field($model, 'password')->passwordInput([
                    'placeholder' => $isNew ? 'Min. 8 characters' : 'Leave blank to keep current'
                ])->label($isNew ? 'Password' : 'New password') ?>
            </div>
            <div class="col-6">
                <?= $form->field($model, 'password_repeat')->passwordInput([
                    'placeholder' => 'Repeat password'
                ])->label('Confirm password') ?>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div class="row g-3 mb-4">
            <div class="col-6">
                <?= $form->field($model, 'role')->dropDownList([
                    \app\models\User::ROLE_ADMIN    => 'Admin',
                    \app\models\User::ROLE_OPERATOR => 'Operator',
                ]) ?>
            </div>
            <div class="col-6">
                <?= $form->field($model, 'status')->dropDownList([
                    \app\models\User::STATUS_ACTIVE   => 'Active',
                    \app\models\User::STATUS_INACTIVE => 'Inactive',
                ]) ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-2">
            <?= Html::submitButton(
                '<i class="bi bi-floppy me-1"></i>' . ($isNew ? 'Create user' : 'Save changes'),
                ['class' => 'btn btn-success']
            ) ?>
            <a href="<?= Url::to(['/user/index']) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>

    <?php ActiveForm::end(); ?>
    </div>
</div>

<!-- API Tokens panel (not shown on create) -->
<?php if (!$isNew && ($isAdmin || $isOwnProfile)): ?>
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">API tokens</span>
        <button class="btn btn-sm btn-outline-secondary" id="btn-gen-token" onclick="generateToken()">
            <i class="bi bi-plus me-1"></i>Generate token
        </button>
    </div>
    <div class="panel-body" id="tokens-list">
        <?php
        $tokens = $model->apiTokens;
        if (empty($tokens)): ?>
            <div class="text-center py-4 text-muted" style="font-size:13px;" id="no-tokens">
                No API tokens yet. Generate one to use the REST API.
            </div>
        <?php else: ?>
            <?php foreach ($tokens as $token): ?>
                <div class="pair-row" id="token-row-<?= $token->id ?>">
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:500;"><?= Html::encode($token->label) ?></div>
                        <div style="font-family:monospace;font-size:11px;color:#9BA3B2;margin-top:2px;">
                            <?= substr($token->token, 0, 12) ?>••••••••••••••••••••••••••••••••••••
                        </div>
                        <div style="font-size:11px;color:#9BA3B2;margin-top:2px;">
                            Created: <?= Yii::$app->formatter->asRelativeTime($token->created_at) ?>
                            <?= $token->last_used_at ? ' · Last used: ' . Yii::$app->formatter->asRelativeTime($token->last_used_at) : ' · Never used' ?>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger"
                            onclick="revokeToken(<?= $token->id ?>)">
                        <i class="bi bi-trash"></i> Revoke
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</div>
</div>

<script>
const csrfToken = '<?= Yii::$app->request->getCsrfToken() ?>';

function generateToken() {
    const label = prompt('Token label (e.g. "Postman", "CI Server"):', 'Default');
    if (!label) return;

    const btn = document.getElementById('btn-gen-token');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('<?= Url::to(['/user/generate-token', 'id' => $model->id ?? 0]) ?>', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'label=' + encodeURIComponent(label)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Show token in a copyable alert
            const alert = document.createElement('div');
            alert.className = 'alert alert-success d-flex align-items-center gap-2 mx-4 mt-3';
            alert.style.fontFamily = 'monospace';
            alert.style.fontSize = '12px';
            alert.innerHTML = `
                <i class="bi bi-key-fill"></i>
                <div style="flex:1;word-break:break-all;">
                    <strong>Copy now — not shown again:</strong><br>
                    <span class="new-tok">${data.token}</span>
                </div>
                <button class="btn btn-sm btn-outline-success" onclick="navigator.clipboard.writeText(this.closest('.alert').querySelector('.new-tok').textContent);this.innerHTML='<i class=\\'bi bi-check\\'></i>'">
                    <i class="bi bi-clipboard"></i>
                </button>`;
            document.getElementById('tokens-list').prepend(alert);
            document.getElementById('no-tokens')?.remove();
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus me-1"></i>Generate token';
    });
}

function revokeToken(tokenId) {
    if (!confirm('Revoke this token? Any clients using it will lose access.')) return;

    fetch('<?= Url::to(['/user/revoke-token']) ?>?id=' + tokenId, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('token-row-' + tokenId)?.remove();
        }
    });
}
</script>