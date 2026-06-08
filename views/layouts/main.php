<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \yii\helpers\Html::encode($this->title) ?> — SyncBridge</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            --sb-green:      #1D9E75;
            --sb-green-dark: #0F6E56;
            --sb-blue:       #378ADD;
            --sb-sidebar-w:  240px;
            --sb-bg:         #F4F6F8;
        }
        body { background: var(--sb-bg); font-family: 'Segoe UI', system-ui, sans-serif; font-size: 14px; }

        /* ── Sidebar ─────────────────────────────────────── */
        #sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sb-sidebar-w);
            background: #fff;
            border-right: 1px solid #E8EAF0;
            display: flex; flex-direction: column;
            z-index: 100;
        }
        .sb-brand {
            padding: 18px 20px;
            border-bottom: 1px solid #E8EAF0;
            display: flex; align-items: center; gap: 10px;
        }
        .sb-brand-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--sb-green);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .sb-brand-icon i { color: #fff; font-size: 16px; }
        .sb-brand-name { font-weight: 600; font-size: 15px; color: #1a1a2e; letter-spacing: -0.02em; }
        .sb-brand-sub  { font-size: 10px; color: #9BA3B2; }
        .sb-nav { flex: 1; padding: 10px 0; overflow-y: auto; }
        .sb-section-label {
            font-size: 10px; font-weight: 600; color: #9BA3B2;
            text-transform: uppercase; letter-spacing: 0.07em;
            padding: 12px 20px 4px;
        }
        .sb-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 20px;
            color: #5A6478; font-size: 13.5px;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: background .15s, color .15s;
        }
        .sb-link:hover { background: #F4F6F8; color: #1a1a2e; }
        .sb-link.active { background: #EBF7F3; color: var(--sb-green); border-left-color: var(--sb-green); font-weight: 500; }
        .sb-link i { width: 18px; text-align: center; font-size: 15px; }
        .sb-footer { padding: 14px 20px; border-top: 1px solid #E8EAF0; font-size: 11px; color: #9BA3B2; }

        /* ── Main content ────────────────────────────────── */
        #main { margin-left: var(--sb-sidebar-w); min-height: 100vh; }
        .topbar {
            background: #fff; border-bottom: 1px solid #E8EAF0;
            padding: 14px 28px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }
        .topbar-title { font-size: 16px; font-weight: 600; color: #1a1a2e; }
        .page-body { padding: 24px 28px; }

        /* ── Flash alerts ─────────────────────────────────── */
        .flash-wrap { margin-bottom: 18px; }

        /* ── Metric cards ─────────────────────────────────── */
        .metric-card {
            background: #fff; border-radius: 10px;
            border: 1px solid #E8EAF0;
            padding: 18px 20px;
        }
        .metric-card .label { font-size: 12px; color: #9BA3B2; margin-bottom: 6px; }
        .metric-card .value { font-size: 26px; font-weight: 600; color: #1a1a2e; line-height: 1; }
        .metric-card .sub   { font-size: 11px; color: #9BA3B2; margin-top: 5px; }

        /* ── Table / card panels ──────────────────────────── */
        .panel { background: #fff; border-radius: 10px; border: 1px solid #E8EAF0; margin-bottom: 20px; }
        .panel-header {
            padding: 14px 18px;
            border-bottom: 1px solid #E8EAF0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .panel-title { font-size: 13px; font-weight: 600; color: #1a1a2e; }
        .panel-body { padding: 0; }

        /* ── Pair rows ────────────────────────────────────── */
        .pair-row {
            padding: 14px 18px;
            border-bottom: 1px solid #F0F2F5;
            display: flex; align-items: flex-start; gap: 12px;
        }
        .pair-row:last-child { border-bottom: none; }
        .pair-row.pair-error { border-left: 3px solid #E24B4A; }
        .db-pill {
            display: inline-flex; align-items: center; gap: 6px;
            background: #F4F6F8; border-radius: 6px;
            padding: 4px 10px; font-size: 12px; font-weight: 500;
        }
        .db-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .db-dot-local  { background: var(--sb-blue); }
        .db-dot-cloud  { background: var(--sb-green); }
        .pair-meta { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 8px; }
        .pair-meta-item label { font-size: 10px; color: #9BA3B2; display: block; }
        .pair-meta-item span  { font-size: 12px; font-weight: 500; color: #3a3f52; }
        .error-note {
            margin-top: 8px; padding: 7px 10px;
            background: #FEF2F2; border-radius: 6px;
            font-size: 11.5px; color: #B91C1C;
        }
        .error-note i { margin-right: 4px; }

        /* ── Log rows ─────────────────────────────────────── */
        .log-row {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 9px 18px; border-bottom: 1px solid #F0F2F5; font-size: 12px;
        }
        .log-row:last-child { border-bottom: none; }
        .log-msg { flex: 1; color: #3a3f52; font-family: 'Courier New', monospace; }
        .log-time { color: #9BA3B2; white-space: nowrap; padding-top: 1px; }

        /* ── Connection cards ─────────────────────────────── */
        .conn-card {
            background: #fff; border-radius: 10px;
            border: 1px solid #E8EAF0; padding: 16px;
        }
        .conn-card.unreachable { border-color: #FECACA; }
        .conn-host { font-size: 11px; color: #9BA3B2; font-family: monospace; margin-top: 2px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div id="sidebar">
    <div class="sb-brand">
        <div class="sb-brand-icon"><i class="bi bi-arrow-left-right"></i></div>
        <div>
            <div class="sb-brand-name">SyncBridge</div>
            <div class="sb-brand-sub">DB Sync Orchestrator</div>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="sb-section-label">Overview</div>
        <a class="sb-link <?= Yii::$app->controller->id === 'dashboard' ? 'active' : '' ?>"
           href="<?= \yii\helpers\Url::to(['/dashboard/index']) ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>

        <div class="sb-section-label">Sync</div>
        <a class="sb-link <?= Yii::$app->controller->id === 'sync-pair' ? 'active' : '' ?>"
           href="<?= \yii\helpers\Url::to(['/sync-pair/index']) ?>">
            <i class="bi bi-arrow-repeat"></i> Sync Pairs
        </a>
        
        <!-- <div class="sb-section-label">Monitor</div> -->
        <a class="sb-link <?= Yii::$app->controller->id === 'monitor' ? 'active' : '' ?>"
           href="<?= \yii\helpers\Url::to(['/monitor/index']) ?>">
            <i class="bi bi-activity"></i> Monitor
        </a>

        <div class="sb-section-label">Configuration</div>
        <a class="sb-link <?= Yii::$app->controller->id === 'connection' ? 'active' : '' ?>"
           href="<?= \yii\helpers\Url::to(['/connection/index']) ?>">
            <i class="bi bi-database"></i> Connections
        </a>

        <div class="sb-section-label">Account</div>
        <a class="sb-link <?= Yii::$app->controller->id === 'user' ? 'active' : '' ?>"
           href="<?= \yii\helpers\Url::to(['/user/index']) ?>">
            <i class="bi bi-people"></i> Users
        </a>
    </nav>

    <div class="sb-footer">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:12px;color:#3a3f52;font-weight:500;">
                    <?= Yii::$app->user->identity->username ?? '' ?>
                </div>
                <div style="font-size:10px;color:#9BA3B2;margin-top:1px;text-transform:uppercase;letter-spacing:.05em;">
                    <?= Yii::$app->user->identity->role ?? '' ?>
                </div>
            </div>
            <a href="<?= \yii\helpers\Url::to(['/site/logout']) ?>"
                data-method="post"
                title="Sign out"
                style="color:#9BA3B2;font-size:16px;text-decoration:none;"
                onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Hidden logout form (POST required by Yii2) -->
    <form id="logout-form" method="post" action="<?= \yii\helpers\Url::to(['/site/logout']) ?>" style="display:none;">
        <?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
    </form>
    
</div>

<!-- Main -->
<div id="main">
    <div class="topbar">
        <div class="topbar-title"><?= $this->title ?? 'Dashboard' ?></div>
        <div class="d-flex gap-2">
            <a href="<?= \yii\helpers\Url::to(['/connection/create']) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-plus-circle me-1"></i>Register DB
            </a>
            <a href="<?= \yii\helpers\Url::to(['/sync-pair/create']) ?>" class="btn btn-sm" style="background:var(--sb-green);color:#fff;">
                <i class="bi bi-plus-circle me-1"></i>New Sync Pair
            </a>
        </div>
    </div>

    <div class="page-body">
        <!-- Flash messages -->
        <div class="flash-wrap">
            <?php foreach (['success','danger','warning','info'] as $type): ?>
                <?php if (Yii::$app->session->hasFlash($type)): ?>
                    <div class="alert alert-<?= $type ?> alert-dismissible fade show" role="alert">
                        <?= Yii::$app->session->getFlash($type) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?= $content ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $this->endBody() ?>
</body>
</html>