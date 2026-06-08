<?php
/** @var yii\web\View $this */
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Dashboard';
?>

<!-- ── Metric row ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="metric-card">
            <div class="label">Active sync pairs</div>
            <div class="value"><?= $activePairs ?></div>
            <div class="sub"><?= $errorPairs > 0 ? "<span style='color:#E24B4A'>{$errorPairs} with errors</span>" : 'All healthy' ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div class="label">Records synced today</div>
            <div class="value"><?= number_format($recordsToday) ?></div>
            <div class="sub">across all pairs</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div class="label">Connections</div>
            <div class="value"><?= $reachable ?> / <?= $totalConns ?></div>
            <div class="sub">reachable right now</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div class="label">Errors (24 h)</div>
            <div class="value" style="<?= $errors24h > 0 ? 'color:#E24B4A' : '' ?>"><?= $errors24h ?></div>
            <div class="sub"><?= $errors24h > 0 ? 'Check logs below' : 'No errors' ?></div>
        </div>
    </div>
</div>

<!-- ── Sync pairs panel ───────────────────────────────────── -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Sync pairs</span>
        <div class="d-flex gap-2">
            <!-- Manual trigger all -->
            <button class="btn btn-sm btn-outline-success" id="btn-sync-all" onclick="triggerAll()">
                <i class="bi bi-arrow-repeat me-1"></i>Run all now
            </button>
            <a href="<?= Url::to(['/sync-pair/create']) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-plus me-1"></i>New pair
            </a>
        </div>
    </div>
    <div class="panel-body" id="pairs-container">
        <?php if (empty($pairs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-arrow-repeat" style="font-size:2rem;"></i>
                <p class="mt-2">No sync pairs yet. <a href="<?= Url::to(['/sync-pair/create']) ?>">Create one →</a></p>
            </div>
        <?php else: ?>
            <?php foreach ($pairs as $pair): ?>
                <?php
                    $lastJob = \app\models\SyncJob::find()
                        ->where(['pair_id' => $pair->id])
                        ->orderBy(['created_at' => SORT_DESC])
                        ->one();
                ?>
                <div class="pair-row <?= $pair->status === 'error' ? 'pair-error' : '' ?>" id="pair-row-<?= $pair->id ?>">
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
                                <span class="text-muted" style="font-size:12px;">(<?= Html::encode($pair->label) ?>)</span>
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
                                <label>Last synced</label>
                                <span><?= $pair->last_synced_at ? Yii::$app->formatter->asRelativeTime($pair->last_synced_at) : '—' ?></span>
                            </div>
                            <div class="pair-meta-item">
                                <label>Next sync</label>
                                <span><?= $pair->next_sync_at ? Yii::$app->formatter->asRelativeTime($pair->next_sync_at) : '—' ?></span>
                            </div>
                            <?php if ($lastJob): ?>
                            <div class="pair-meta-item">
                                <label>Last job</label>
                                <span>
                                    ↑<?= $lastJob->records_pushed ?>
                                    <?php if ($lastJob->records_deleted > 0): ?> ✕<?= $lastJob->records_deleted ?><?php endif; ?>
                                    <?php if ($lastJob->conflicts > 0): ?> <span style="color:#f59e0b;">⚡<?= $lastJob->conflicts ?></span><?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($pair->status === 'error' && $pair->last_error): ?>
                            <div class="error-note">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <?= Html::encode($pair->last_error) ?>
                                <?php if ($pair->retry_count > 0): ?>
                                    <span class="ms-2 text-muted">(attempt <?= $pair->retry_count ?>/<?= \app\models\SyncJob::MAX_ATTEMPTS ?>)</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Status + actions -->
                    <div class="d-flex align-items-start gap-2 flex-shrink-0">
                        <span class="badge bg-<?= $pair->getStatusBadgeClass() ?> rounded-pill" style="font-size:11px;padding:5px 10px;">
                            <?= $pair->getStatusLabel() ?>
                        </span>

                        <!-- Manual trigger this pair -->
                        <button class="btn btn-sm btn-outline-success" onclick="triggerPair(<?= $pair->id ?>)" title="Run sync now">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>

                        <!-- Toggle active/paused -->
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

                        <a href="<?= Url::to(['/sync-pair/update', 'id' => $pair->id]) ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" action="<?= Url::to(['/sync-pair/delete', 'id' => $pair->id]) ?>"
                              onsubmit="return confirm('Delete this sync pair?')">
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

<!-- ── Toast notification ─────────────────────────────────── -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="sync-toast" class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="sync-toast-msg">Sync triggered.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- ── Two-col: connections + logs ───────────────────────── -->
<div class="row g-3">
    <div class="col-md-5">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title">Connections</span>
                <a href="<?= Url::to(['/connection/index']) ?>" class="btn btn-sm btn-outline-secondary">
                    Manage <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="panel-body p-3">
                <div class="row g-2">
                    <?php foreach ($connections as $conn): ?>
                        <div class="col-12">
                            <div class="conn-card <?= $conn->is_reachable ? '' : 'unreachable' ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge <?= $conn->type === 'local' ? 'text-bg-primary' : 'text-bg-success' ?>"
                                              style="font-size:9px;letter-spacing:.05em;"><?= strtoupper($conn->type) ?></span>
                                        <div class="fw-semibold mt-1"><?= Html::encode($conn->label) ?></div>
                                        <div class="conn-host"><?= Html::encode($conn->host) ?>:<?= $conn->port ?> / <?= Html::encode($conn->dbname) ?></div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1" style="font-size:12px;">
                                        <?php if ($conn->is_reachable): ?>
                                            <i class="bi bi-circle-fill text-success" style="font-size:8px;"></i>
                                            <span class="text-success">OK</span>
                                        <?php else: ?>
                                            <i class="bi bi-circle-fill text-danger" style="font-size:8px;"></i>
                                            <span class="text-danger">Unreachable</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title">Recent logs</span>
            </div>
            <div class="panel-body" style="max-height: 420px; overflow-y: auto;">
                <?php foreach ($logs as $log): ?>
                    <div class="log-row">
                        <span class="badge text-bg-<?= $log->getLevelBadgeClass() ?>" style="font-size:10px;padding:3px 7px;flex-shrink:0;">
                            <?= strtoupper($log->level) ?>
                        </span>
                        <span class="log-msg"><?= Html::encode($log->message) ?></span>
                        <span class="log-time"><?= Yii::$app->formatter->asRelativeTime($log->created_at) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <div class="text-center text-muted py-4">No logs yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= Yii::$app->request->getCsrfToken() ?>';

function showToast(msg, type = 'success') {
    const toast = document.getElementById('sync-toast');
    document.getElementById('sync-toast-msg').textContent = msg;
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    bootstrap.Toast.getOrCreateInstance(toast, { delay: 4000 }).show();
}

function triggerPair(pairId) {
    const btn = event.currentTarget;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('/sync/start?id=' + pairId, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken, 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        const job = data.jobs?.[0];
        if (job?.status === 'completed') {
            showToast(`Sync complete — pushed: ${job.records_pushed}, deleted: ${job.records_deleted}`, 'success');
        } else {
            showToast(`Sync failed: ${job?.error || 'Unknown error'}`, 'danger');
        }
        setTimeout(() => location.reload(), 1500);
    })
    .catch(() => {
        showToast('Request failed — check console.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
    });
}

function triggerAll() {
    const btn = document.getElementById('btn-sync-all');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Running…';

    fetch('/sync/start', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken, 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        showToast(`${data.pairs_run} pair(s) synced.`, 'success');
        setTimeout(() => location.reload(), 1500);
    })
    .catch(() => {
        showToast('Request failed — check console.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Run all now';
    });
}
</script>