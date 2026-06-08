<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Sync Pairs';
?>

<div class="mb-3 d-flex justify-content-end">
    <a href="<?= Url::to(['/sync-pair/create']) ?>" class="btn btn-sm btn-success">
        <i class="bi bi-plus-circle me-1"></i>New sync pair
    </a>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title">All sync pairs</span>
    </div>
    <div class="panel-body">
        <?php if (empty($pairs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-arrow-repeat" style="font-size:2.5rem;"></i>
                <p class="mt-2">No sync pairs yet.</p>
                <a href="<?= Url::to(['/sync-pair/create']) ?>" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i>Create your first pair
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($pairs as $pair): ?>
                <div class="pair-row <?= $pair->status === 'error' ? 'pair-error' : '' ?>">
                    <div style="flex:1;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="db-pill">
                                <span class="db-dot db-dot-local"></span>
                                <?= Html::encode($pair->localConn->label) ?>
                            </span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="db-pill">
                                <span class="db-dot db-dot-cloud"></span>
                                <?= Html::encode($pair->cloudConn->label) ?>
                            </span>
                            <?php if ($pair->label): ?>
                                <span class="text-muted" style="font-size:12px;">
                                    (<?= Html::encode($pair->label) ?>)
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="pair-meta">
                            <div class="pair-meta-item">
                                <label>Tables</label>
                                <span><?= Html::encode($pair->getTablesDisplay()) ?></span>
                            </div>
                            <div class="pair-meta-item">
                                <label>Interval</label>
                                <span>Every <?= $pair->interval_minutes ?> min</span>
                            </div>
                            <div class="pair-meta-item">
                                <label>Conflict strategy</label>
                                <span><?= Html::encode($pair->getConflictOptions()[$pair->conflict_strategy] ?? $pair->conflict_strategy) ?></span>
                            </div>
                            <div class="pair-meta-item">
                                <label>Last synced</label>
                                <span><?= $pair->last_synced_at
                                    ? Yii::$app->formatter->asRelativeTime($pair->last_synced_at)
                                    : '—' ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($pair->status === 'error' && $pair->last_error): ?>
                            <div class="error-note mt-2">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <?= Html::encode($pair->last_error) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex align-items-start gap-2 flex-shrink-0">
                        <span class="badge bg-<?= $pair->getStatusBadgeClass() ?> rounded-pill"
                              style="font-size:11px;padding:5px 10px;">
                            <?= $pair->getStatusLabel() ?>
                        </span>

                        <!-- Toggle -->
                        <?php if ($pair->status === 'active'): ?>
                            <form method="post" action="<?= Url::to(['/sync-pair/toggle', 'id' => $pair->id]) ?>">
                                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Pause">
                                    <i class="bi bi-pause-fill"></i>
                                </button>
                            </form>
                        <?php elseif (in_array($pair->status, ['paused', 'error'])): ?>
                            <form method="post" action="<?= Url::to(['/sync-pair/toggle', 'id' => $pair->id]) ?>">
                                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Resume">
                                    <i class="bi bi-play-fill"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                        <a href="<?= Url::to(['/sync-pair/update', 'id' => $pair->id]) ?>"
                           class="btn btn-sm btn-outline-secondary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>

                        <form method="post" action="<?= Url::to(['/sync-pair/delete', 'id' => $pair->id]) ?>"
                              onsubmit="return confirm('Delete this sync pair? This cannot be undone.')">
                            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>