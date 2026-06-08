<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Monitor';

// Build chart data from hourly activity
$chartLabels = [];
$chartData   = [];
foreach ($hourlyActivity as $row) {
    $chartLabels[] = date('H:i', strtotime($row['hour']));
    $chartData[]   = (int) $row['records'];
}
// Fill missing hours with 0 if fewer than 24 data points
if (count($chartLabels) < 2) {
    $chartLabels = ['00:00', '06:00', '12:00', '18:00', '23:00'];
    $chartData   = [0, 0, 0, 0, 0];
}
?>

<style>
.queue-stat { text-align:center; padding: 16px 10px; }
.queue-stat .qs-val { font-size: 32px; font-weight: 700; line-height: 1; }
.queue-stat .qs-label { font-size: 11px; color: #9BA3B2; margin-top: 4px; text-transform: uppercase; letter-spacing: .06em; }
.job-row { display: flex; align-items: center; gap: 10px; padding: 9px 16px; border-bottom: 1px solid #F0F2F5; font-size: 12.5px; }
.job-row:last-child { border-bottom: none; }
.job-pair { flex: 1; color: #3a3f52; }
.job-pair small { color: #9BA3B2; font-size: 11px; display: block; }
.live-dot { width: 8px; height: 8px; border-radius: 50%; background: #1D9E75; display: inline-block; animation: pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
.filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.filter-bar .btn { font-size: 12px; padding: 4px 12px; }
.log-stream { max-height: 320px; overflow-y: auto; font-family: monospace; font-size: 12px; }
.log-stream .log-row { border-bottom: 1px solid #F0F2F5; padding: 7px 14px; display: flex; gap: 10px; }
.log-stream .log-row:last-child { border-bottom: none; }
.pair-stat-row { display: flex; align-items: center; padding: 10px 16px; border-bottom: 1px solid #F0F2F5; gap: 12px; }
.pair-stat-row:last-child { border-bottom: none; }
.pair-stat-bars { flex: 1; }
.mini-bar-wrap { height: 6px; background: #F0F2F5; border-radius: 3px; overflow: hidden; margin-top: 4px; }
.mini-bar { height: 100%; border-radius: 3px; background: #1D9E75; transition: width .4s; }
</style>

<!-- ── Live indicator + header actions ────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
        <span class="live-dot"></span>
        <span style="font-size:12px;color:#9BA3B2;" id="last-poll">Live — refreshing every 10s</span>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="purgeQueue()">
            <i class="bi bi-trash me-1"></i>Purge old jobs
        </button>
        <a href="<?= Url::to(['/monitor/logs-export']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Export logs CSV
        </a>
    </div>
</div>

<!-- ── Queue depth cards ──────────────────────────────────── -->
<div class="panel mb-4">
    <div class="panel-header">
        <span class="panel-title">Queue status</span>
    </div>
    <div class="row g-0 text-center" style="border-bottom: 1px solid #F0F2F5;">
        <div class="col-3" style="border-right: 1px solid #F0F2F5;">
            <div class="queue-stat">
                <div class="qs-val" id="q-waiting"><?= $queueDepth ?></div>
                <div class="qs-label">Waiting</div>
            </div>
        </div>
        <div class="col-3" style="border-right: 1px solid #F0F2F5;">
            <div class="queue-stat">
                <div class="qs-val text-primary" id="q-running"><?= $running ?></div>
                <div class="qs-label">Running</div>
            </div>
        </div>
        <div class="col-3" style="border-right: 1px solid #F0F2F5;">
            <div class="queue-stat">
                <div class="qs-val <?= $failed > 0 ? 'text-danger' : '' ?>" id="q-failed"><?= $failed ?></div>
                <div class="qs-label">Failed</div>
            </div>
        </div>
        <div class="col-3">
            <div class="queue-stat">
                <div class="qs-val text-success"><?= count($pairs) ?></div>
                <div class="qs-label">Total pairs</div>
            </div>
        </div>
    </div>

    <!-- Throughput sparkline -->
    <div style="padding: 16px 18px;">
        <div style="font-size:12px;color:#9BA3B2;margin-bottom:8px;">Records synced — last 24 hours</div>
        <canvas id="throughput-chart" height="60"></canvas>
    </div>
</div>

<!-- ── Two column: pair stats + queue jobs ────────────────── -->
<div class="row g-3 mb-3">

    <!-- Pair stats -->
    <div class="col-md-5">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Pair activity (24 h)</span>
            </div>
            <?php
            $maxRecords = max(1, max(array_column($pairStats, 'total_records') ?: [1]));
            ?>
            <?php foreach ($pairs as $pair): ?>
                <?php $stat = $pairStats[$pair->id] ?? []; ?>
                <div class="pair-stat-row">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:12.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= Html::encode($pair->localConn->label) ?>
                            <span style="color:#9BA3B2;">→</span>
                            <?= Html::encode($pair->cloudConn->label) ?>
                        </div>
                        <div class="mini-bar-wrap">
                            <div class="mini-bar" style="width:<?= min(100, round(($stat['total_records'] ?? 0) / $maxRecords * 100)) ?>%"></div>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:13px;font-weight:600;"><?= number_format($stat['total_records'] ?? 0) ?></div>
                        <div style="font-size:10px;color:#9BA3B2;">records</div>
                    </div>
                    <div>
                        <span class="badge bg-<?= $pair->getStatusBadgeClass() ?> rounded-pill" style="font-size:10px;">
                            <?= $pair->status ?>
                        </span>
                        <?php if (($stat['errors'] ?? 0) > 0): ?>
                            <span class="badge text-bg-danger ms-1" style="font-size:10px;"><?= $stat['errors'] ?> err</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($pairs)): ?>
                <div class="text-center py-4 text-muted" style="font-size:13px;">No pairs configured.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Queue jobs -->
    <div class="col-md-7">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Recent queue jobs</span>
                <div class="d-flex gap-1">
                    <?php foreach (['all','waiting','reserved','done','failed'] as $s): ?>
                        <button class="btn btn-sm btn-outline-secondary filter-status <?= $s === 'all' ? 'active' : '' ?>"
                                style="font-size:11px;padding:2px 8px;"
                                data-status="<?= $s ?>"
                                onclick="filterJobs('<?= $s ?>',this)">
                            <?= ucfirst($s) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="max-height:340px;overflow-y:auto;" id="jobs-list">
                <?php if (empty($queueJobs)): ?>
                    <div class="text-center py-4 text-muted" style="font-size:13px;">No queue jobs yet.</div>
                <?php else: ?>
                    <?php foreach ($queueJobs as $job): ?>
                        <div class="job-row" data-status="<?= $job['status'] ?>">
                            <?php
                            switch ($job['status']) {
                                case 'done':
                                    $badgeClass = 'success';
                                    break;
                                case 'reserved':
                                    $badgeClass = 'primary';
                                    break;
                                case 'failed':
                                    $badgeClass = 'danger';
                                    break;
                                default:
                                    $badgeClass = 'secondary';
                            }
                            ?>
                            <span class="badge text-bg-<?= $badgeClass ?>" style="font-size:10px;flex-shrink:0;">
                                <?= strtoupper($job['status']) ?>
                            </span>

                            <div class="job-pair">
                                <?php if ($job['local_label']): ?>
                                    <?= Html::encode($job['local_label']) ?> → <?= Html::encode($job['cloud_label']) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                                <small>
                                    Job #<?= $job['id'] ?> ·
                                    attempt <?= $job['attempt'] ?> ·
                                    <?= $job['pushed_at_dt'] ? date('d M H:i', strtotime($job['pushed_at_dt'])) : '—' ?>
                                </small>
                                <?php if ($job['status'] === 'failed' && $job['error']): ?>
                                    <small style="color:#E24B4A;"><?= Html::encode(substr($job['error'], 0, 80)) ?><?= strlen($job['error']) > 80 ? '…' : '' ?></small>
                                <?php endif; ?>
                            </div>

                            <?php if ($job['status'] === 'failed' && $job['pair_id']): ?>
                                <button class="btn btn-sm btn-outline-warning" style="font-size:11px;"
                                        onclick="retryJob(<?= $job['id'] ?>,this)">
                                    <i class="bi bi-arrow-clockwise"></i> Retry
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Live log stream ────────────────────────────────────── -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Live log stream</span>
        <div class="d-flex gap-2 align-items-center">
            <select id="log-level-filter" class="form-select form-select-sm" style="width:auto;font-size:12px;" onchange="renderLogs()">
                <option value="">All levels</option>
                <option value="success">Success</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
            </select>
            <select id="log-pair-filter" class="form-select form-select-sm" style="width:auto;font-size:12px;" onchange="renderLogs()">
                <option value="">All pairs</option>
                <?php foreach ($pairs as $pair): ?>
                    <option value="<?= $pair->id ?>">
                        <?= Html::encode($pair->localConn->label) ?> → <?= Html::encode($pair->cloudConn->label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="log-stream" id="log-stream">
        <div class="text-center py-3 text-muted" style="font-size:12px;">Loading logs…</div>
    </div>
</div>

<!-- ── Toast ──────────────────────────────────────────────── -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="m-toast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="m-toast-msg"></div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Throughput chart ───────────────────────────────────────
const ctx = document.getElementById('throughput-chart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            data: <?= json_encode($chartData) ?>,
            backgroundColor: '#1D9E75',
            borderRadius: 3,
            barPercentage: 0.6,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#9BA3B2' } },
            y: { grid: { color: '#F0F2F5' }, ticks: { font: { size: 10 }, color: '#9BA3B2', precision: 0 }, beginAtZero: true }
        },
        animation: false,
        responsive: true,
        maintainAspectRatio: true,
    }
});

// ── Live poll state ────────────────────────────────────────
let allLogs = [];

function showToast(msg, type = 'success') {
    const t = document.getElementById('m-toast');
    document.getElementById('m-toast-msg').textContent = msg;
    t.className = `toast align-items-center text-bg-${type} border-0`;
    bootstrap.Toast.getOrCreateInstance(t, { delay: 3500 }).show();
}

// ── Log rendering ──────────────────────────────────────────
function renderLogs() {
    const levelFilter = document.getElementById('log-level-filter').value;
    const pairFilter  = document.getElementById('log-pair-filter').value;
    const stream      = document.getElementById('log-stream');

    let filtered = allLogs.filter(l => {
        if (levelFilter && l.level !== levelFilter) return false;
        if (pairFilter  && String(l.pair_id) !== pairFilter) return false;
        return true;
    });

    if (!filtered.length) {
        stream.innerHTML = '<div class="text-center py-3 text-muted" style="font-size:12px;">No logs match the current filter.</div>';
        return;
    }

    const badgeMap = { success: 'success', error: 'danger', warning: 'warning', info: 'info' };

    stream.innerHTML = filtered.map(l => `
        <div class="log-row">
            <span class="badge text-bg-${badgeMap[l.level] || 'secondary'}" style="font-size:10px;flex-shrink:0;">${l.level.toUpperCase()}</span>
            <span style="flex:1;color:#3a3f52;">${escHtml(l.message)}</span>
            <span style="color:#9BA3B2;white-space:nowrap;">${l.created_at}</span>
        </div>
    `).join('');
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Queue job filter ───────────────────────────────────────
function filterJobs(status, btn) {
    document.querySelectorAll('.filter-status').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#jobs-list .job-row').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}

// ── Retry failed job ───────────────────────────────────────
function retryJob(jobId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch('/monitor/retry-job?id=' + jobId, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.success) showToast('Retry queued — job #' + d.new_job_id);
            else showToast(d.error || 'Retry failed', 'danger');
        })
        .catch(() => showToast('Request failed', 'danger'));
}

// ── Purge completed jobs ───────────────────────────────────
function purgeQueue() {
    if (!confirm('Remove completed jobs older than 7 days?')) return;
    fetch('/monitor/purge-queue', { method: 'POST' })
        .then(r => r.json())
        .then(d => showToast(`Purged ${d.deleted} old job(s).`));
}

// ── Live poll ──────────────────────────────────────────────
function poll() {
    fetch('/monitor/poll')
        .then(r => r.json())
        .then(data => {
            // Update queue stats
            document.getElementById('q-waiting').textContent = data.queueStats.depth;
            document.getElementById('q-running').textContent = data.queueStats.running;
            const failedEl = document.getElementById('q-failed');
            failedEl.textContent = data.queueStats.failed;
            failedEl.className   = 'qs-val' + (data.queueStats.failed > 0 ? ' text-danger' : '');

            // Update logs
            allLogs = data.logData;
            renderLogs();

            document.getElementById('last-poll').textContent =
                'Live — last updated ' + new Date().toLocaleTimeString('en-MY');
        })
        .catch(() => {
            document.getElementById('last-poll').textContent = 'Poll failed — retrying…';
        });
}

poll();
setInterval(poll, 10000);
</script>