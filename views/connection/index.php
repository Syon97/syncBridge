<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Connections';
?>

<div class="row g-3">
    <?php foreach ($connections as $conn): ?>
        <div class="col-md-6">
            <div class="conn-card <?= $conn->is_reachable ? '' : 'unreachable' ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge <?= $conn->type === 'local' ? 'text-bg-primary' : 'text-bg-success' ?>" style="font-size:9px;">
                            <?= strtoupper($conn->type) ?>
                        </span>
                        <div class="fw-semibold mt-1 fs-6"><?= Html::encode($conn->label) ?></div>
                        <div class="conn-host"><?= Html::encode($conn->host) ?>:<?= $conn->port ?> / <?= Html::encode($conn->dbname) ?></div>
                    </div>
                    <div class="d-flex align-items-center gap-1" style="font-size:12px;">
                        <?php if ($conn->is_reachable): ?>
                            <i class="bi bi-circle-fill text-success" style="font-size:8px;"></i>
                            <span class="text-success">Reachable</span>
                        <?php else: ?>
                            <i class="bi bi-circle-fill text-danger" style="font-size:8px;"></i>
                            <span class="text-danger">Unreachable</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($conn->last_tested_at): ?>
                    <div style="font-size:11px;color:#9BA3B2;" class="mb-2">
                        Tested: <?= Yii::$app->formatter->asRelativeTime($conn->last_tested_at) ?>
                    </div>
                <?php endif; ?>

                <?php if ($conn->notes): ?>
                    <div style="font-size:12px;color:#6B7280;" class="mb-2"><?= Html::encode($conn->notes) ?></div>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-3">
                    <!-- Test -->
                    <form method="post" action="<?= Url::to(['/connection/test', 'id' => $conn->id]) ?>">
                        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plug me-1"></i>Test
                        </button>
                    </form>
                    <!-- Edit -->
                    <a href="<?= Url::to(['/connection/update', 'id' => $conn->id]) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    <!-- Delete -->
                    <form method="post" action="<?= Url::to(['/connection/delete', 'id' => $conn->id]) ?>"
                        onsubmit="return confirm('Delete connection \'<?= Html::encode($conn->label) ?>\'?')">
                        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($connections)): ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="bi bi-database" style="font-size:2.5rem;"></i>
            <p class="mt-2">No connections registered yet.</p>
            <a href="<?= Url::to(['/connection/create']) ?>" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i>Register your first connection
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="mt-3">
    <a href="<?= Url::to(['/connection/create']) ?>" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i>Register new connection
    </a>
</div>