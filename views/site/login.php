<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SyncBridge — Sign in</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:      #1D9E75;
            --green-dim:  #0F6E56;
            --green-glow: rgba(29, 158, 117, 0.15);
            --bg:         #0A0C0F;
            --surface:    #111418;
            --border:     rgba(255,255,255,0.07);
            --border-lit: rgba(29,158,117,0.4);
            --text:       #E8EBF0;
            --muted:      #5A6478;
            --mono:       'Space Mono', monospace;
            --sans:       'DM Sans', sans-serif;
        }

        body {
            background: var(--bg);
            font-family: var(--sans);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* ── Animated grid background ─────────────────────── */
        .bg-grid {
            position: fixed; inset: 0; z-index: 0;
            background-image:
                linear-gradient(rgba(29,158,117,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(29,158,117,0.04) 1px, transparent 1px);
            background-size: 48px 48px;
            animation: gridDrift 20s linear infinite;
        }
        @keyframes gridDrift {
            0%   { background-position: 0 0; }
            100% { background-position: 48px 48px; }
        }

        /* ── Glow orb ─────────────────────────────────────── */
        .bg-orb {
            position: fixed;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(29,158,117,0.08) 0%, transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none; z-index: 0;
            animation: orbPulse 4s ease-in-out infinite;
        }
        @keyframes orbPulse {
            0%,100% { transform: translate(-50%,-50%) scale(1);   opacity: 1; }
            50%      { transform: translate(-50%,-50%) scale(1.1); opacity: 0.7; }
        }

        /* ── Card ─────────────────────────────────────────── */
        .card {
            position: relative; z-index: 1;
            width: 420px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 44px 40px 40px;
            box-shadow: 0 0 0 1px rgba(0,0,0,.4), 0 32px 64px rgba(0,0,0,.5);
            animation: cardIn .5s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(24px) scale(.97); }
            to   { opacity: 1; transform: none; }
        }

        /* top accent line */
        .card::before {
            content: '';
            position: absolute; top: -1px; left: 20%; right: 20%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--green), transparent);
            border-radius: 0 0 4px 4px;
        }

        /* ── Brand ────────────────────────────────────────── */
        .brand {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 32px;
        }
        .brand-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: linear-gradient(135deg, var(--green), var(--green-dim));
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 20px var(--green-glow);
            flex-shrink: 0;
        }
        .brand-icon svg { width: 20px; height: 20px; }
        .brand-name {
            font-family: var(--mono);
            font-size: 17px; font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        .brand-tag {
            font-size: 11px; color: var(--muted);
            letter-spacing: 0.04em;
            margin-top: 1px;
        }

        /* ── Heading ──────────────────────────────────────── */
        .heading { margin-bottom: 28px; }
        .heading h1 {
            font-size: 22px; font-weight: 600;
            color: var(--text); letter-spacing: -0.03em;
            line-height: 1.2;
        }
        .heading p {
            font-size: 13px; color: var(--muted);
            margin-top: 5px; font-weight: 300;
        }

        /* ── Form ─────────────────────────────────────────── */
        .field { margin-bottom: 18px; }
        .field label {
            display: block;
            font-size: 11.5px; font-weight: 500;
            color: var(--muted);
            letter-spacing: 0.06em; text-transform: uppercase;
            margin-bottom: 7px;
        }
        .input-wrap { position: relative; }
        .input-wrap svg {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%);
            width: 15px; height: 15px;
            color: var(--muted);
            pointer-events: none;
            transition: color .2s;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 14px 11px 38px;
            font-size: 14px; font-family: var(--sans);
            color: var(--text);
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
            -webkit-appearance: none;
        }
        input::placeholder { color: var(--muted); }
        input:focus {
            border-color: var(--border-lit);
            background: rgba(29,158,117,0.04);
            box-shadow: 0 0 0 3px var(--green-glow);
        }
        input:focus + svg, .input-wrap:focus-within svg {
            color: var(--green);
        }

        /* ── Remember me ──────────────────────────────────── */
        .remember {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 24px; cursor: pointer;
        }
        .remember input[type="checkbox"] {
            width: 16px; height: 16px; padding: 0;
            accent-color: var(--green);
            cursor: pointer;
        }
        .remember span {
            font-size: 13px; color: var(--muted);
            user-select: none;
        }

        /* ── Submit button ────────────────────────────────── */
        .btn-submit {
            width: 100%;
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 13px;
            font-size: 14px; font-weight: 600;
            font-family: var(--sans);
            cursor: pointer;
            letter-spacing: 0.01em;
            transition: background .2s, box-shadow .2s, transform .1s;
            position: relative; overflow: hidden;
        }
        .btn-submit::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,.08) 0%, transparent 100%);
            pointer-events: none;
        }
        .btn-submit:hover {
            background: var(--green-dim);
            box-shadow: 0 4px 20px var(--green-glow);
        }
        .btn-submit:active { transform: scale(.98); }
        .btn-submit:disabled {
            opacity: .6; cursor: not-allowed; transform: none;
        }
        .btn-submit .spinner {
            display: none; width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Error alert ──────────────────────────────────── */
        .alert-error {
            background: rgba(226, 75, 74, 0.1);
            border: 1px solid rgba(226, 75, 74, 0.3);
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 13px; color: #F87171;
            margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 8px;
            animation: shake .4s ease both;
        }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60%  { transform: translateX(-5px); }
            40%,80%  { transform: translateX(5px); }
        }
        .alert-error svg { flex-shrink: 0; margin-top: 1px; }

        /* ── Footer ───────────────────────────────────────── */
        .card-footer {
            margin-top: 28px; padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 12px; color: var(--muted);
            font-family: var(--mono);
        }
        .card-footer span { color: var(--green); }

        /* ── Yii2 error overrides ─────────────────────────── */
        .help-block { font-size: 12px; color: #F87171; margin-top: 5px; }
        .has-error input { border-color: rgba(226,75,74,0.5) !important; }
    </style>
</head>
<body>

<div class="bg-grid"></div>
<div class="bg-orb"></div>

<div class="card">

    <!-- Brand -->
    <div class="brand">
        <div class="brand-icon">
            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 10h14M10 3v14M3 7l4-4 4 4M14 17l4-4-4-4" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div>
            <div class="brand-name">SyncBridge</div>
            <div class="brand-tag">Database Sync Orchestrator</div>
        </div>
    </div>

    <!-- Heading -->
    <div class="heading">
        <h1>Welcome back</h1>
        <p>Sign in to manage your sync pairs and connections.</p>
    </div>

    <?php
    use yii\helpers\Html;
    use yii\bootstrap5\ActiveForm;

    // Show error alert if form has errors
    if ($model->hasErrors()): ?>
        <div class="alert-error">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                <circle cx="7.5" cy="7.5" r="6.5" stroke="#F87171" stroke-width="1.3"/>
                <path d="M7.5 4.5v3.5M7.5 10h.01" stroke="#F87171" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span>Incorrect username or password. Please try again.</span>
        </div>
    <?php endif; ?>

    <?php $form = ActiveForm::begin([
        'id'                     => 'login-form',
        'fieldConfig'            => [
            'template'           => "{input}\n{error}",
            'errorOptions'       => ['class' => 'help-block'],
        ],
        'options' => ['class' => ''],
    ]); ?>

        <!-- Username -->
        <div class="field">
            <label for="loginform-username">Username</label>
            <div class="input-wrap">
                <?= $form->field($model, 'username')->textInput([
                    'id'          => 'loginform-username',
                    'placeholder' => 'your_username',
                    'autofocus'   => true,
                    'autocomplete'=> 'username',
                ])->label(false) ?>
                <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="5.5" r="2.5" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M2.5 13.5c0-3.038 2.462-5.5 5.5-5.5s5.5 2.462 5.5 5.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
            </div>
        </div>

        <!-- Password -->
        <div class="field">
            <label for="loginform-password">Password</label>
            <div class="input-wrap">
                <?= $form->field($model, 'password')->passwordInput([
                    'id'          => 'loginform-password',
                    'placeholder' => '••••••••',
                    'autocomplete'=> 'current-password',
                ])->label(false) ?>
                <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    <circle cx="8" cy="10.5" r="1" fill="currentColor"/>
                </svg>
            </div>
        </div>

        <!-- Remember me -->
        <label class="remember">
            <?= $form->field($model, 'rememberMe')->checkbox([
                'template' => '{input}',
            ]) ?>
            <span>Keep me signed in for 30 days</span>
        </label>

        <!-- Submit -->
        <button type="submit" class="btn-submit" id="btn-login">
            <span class="btn-text">Sign in</span>
            <div class="spinner" id="login-spinner"></div>
        </button>

    <?php ActiveForm::end(); ?>

    <div class="card-footer">
        SyncBridge <span>v0.4.0</span> · Phase 4
    </div>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', function() {
    const btn = document.getElementById('btn-login');
    btn.disabled = true;
    document.querySelector('.btn-text').style.display = 'none';
    document.getElementById('login-spinner').style.display = 'block';
});
</script>
</body>
</html>