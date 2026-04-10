<?php
session_start();
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asian College — Locker Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ── RESET & ROOT ─────────────────────────────── */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
  --navy:       #0c1e3e;
  --navy-mid:   #162d55;
  --navy-light: #1e3d6e;
  --gold:       #c9a84c;
  --gold-light: #e2c97e;
  --gold-pale:  #f5ead4;
  --cream:      #faf8f4;
  --white:      #ffffff;
  --red:        #b52a2a;
  --text-dark:  #0c1e3e;
  --text-mid:   #4a5568;
  --text-light: #8a96a8;
  --error-bg:   #fff0f0;
  --error-text: #b52a2a;
  --error-border: #f0b8b8;
  --input-border: #d8dde8;
  --input-focus:  #1e3d6e;
  --shadow-card: 0 32px 80px rgba(12,30,62,0.18), 0 8px 24px rgba(12,30,62,0.10);
  --shadow-btn:  0 6px 24px rgba(12,30,62,0.35);
  --radius:      14px;
  --radius-sm:   8px;
  --transition:  0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

html, body {
  height: 100%;
  font-family: 'Outfit', sans-serif;
  background: var(--navy);
  overflow: hidden;
}

/* ── FULL-PAGE LAYOUT ─────────────────────────── */
.page {
  display: flex;
  height: 100vh;
  width: 100vw;
  position: relative;
}

/* ── LEFT PANEL ───────────────────────────────── */
.left {
  flex: 1.1;
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 52px 56px;
  overflow: hidden;
  background: var(--navy);
}

/* Layered background effects */
.left-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
}

/* Subtle grid lines */
.left-bg::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(201,168,76,0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(201,168,76,0.05) 1px, transparent 1px);
  background-size: 48px 48px;
}

/* Large glowing orbs */
.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
}
.orb-1 {
  width: 480px; height: 480px;
  background: radial-gradient(circle, rgba(30,61,110,0.9) 0%, transparent 70%);
  top: -120px; right: -120px;
}
.orb-2 {
  width: 360px; height: 360px;
  background: radial-gradient(circle, rgba(201,168,76,0.12) 0%, transparent 70%);
  bottom: -80px; left: -60px;
}
.orb-3 {
  width: 220px; height: 220px;
  background: radial-gradient(circle, rgba(181,42,42,0.08) 0%, transparent 70%);
  top: 40%; left: 30%;
}

/* Decorative arc line */
.arc-line {
  position: absolute;
  width: 600px; height: 600px;
  border: 1px solid rgba(201,168,76,0.10);
  border-radius: 50%;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
}
.arc-line-2 {
  width: 400px; height: 400px;
  border-color: rgba(201,168,76,0.06);
}

/* ── LEFT HEADER ──────────────────────────────── */
.left-header {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 16px;
  animation: fadeSlideDown 0.8s ease both;
}

.logo-wrap {
  width: 56px; height: 56px;
  background: white;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3), 0 0 0 1px rgba(201,168,76,0.3);
  overflow: hidden;
  flex-shrink: 0;
}

.logo-wrap img {
  width: 48px; height: 48px;
  object-fit: contain;
}

.brand-name {
  font-family: 'Cormorant Garamond', serif;
  font-size: 20px;
  font-weight: 600;
  color: white;
  line-height: 1.2;
}
.brand-name span {
  display: block;
  font-family: 'Outfit', sans-serif;
  font-size: 10px;
  font-weight: 400;
  color: var(--gold);
  letter-spacing: 2.5px;
  text-transform: uppercase;
  margin-top: 2px;
}

/* ── LEFT HERO TEXT ───────────────────────────── */
.left-hero {
  position: relative;
  z-index: 2;
  animation: fadeSlideUp 0.9s 0.2s ease both;
}

.left-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 20px;
}
.left-eyebrow::before {
  content: '';
  width: 24px; height: 1px;
  background: var(--gold);
  opacity: 0.6;
}

.left-headline {
  font-family: 'Cormorant Garamond', serif;
  font-size: clamp(36px, 4vw, 52px);
  font-weight: 700;
  color: white;
  line-height: 1.12;
  letter-spacing: -0.5px;
  margin-bottom: 20px;
}
.left-headline em {
  font-style: normal;
  color: var(--gold-light);
}

.left-desc {
  font-size: 14px;
  color: rgba(255,255,255,0.45);
  line-height: 1.7;
  max-width: 340px;
  font-weight: 300;
}

/* ── LEFT FOOTER STATS ────────────────────────── */
.left-stats {
  position: relative;
  z-index: 2;
  display: flex;
  gap: 0;
  animation: fadeSlideUp 0.9s 0.4s ease both;
}

.stat-item {
  padding: 0 28px 0 0;
  border-right: 1px solid rgba(255,255,255,0.1);
  margin-right: 28px;
}
.stat-item:last-child { border-right: none; margin-right: 0; padding-right: 0; }

.stat-num {
  font-family: 'Cormorant Garamond', serif;
  font-size: 28px;
  font-weight: 700;
  color: white;
  line-height: 1;
}
.stat-label {
  font-size: 10px;
  color: rgba(255,255,255,0.35);
  letter-spacing: 1.5px;
  text-transform: uppercase;
  margin-top: 4px;
  font-weight: 400;
}

/* ── DIVIDER ──────────────────────────────────── */
.divider {
  width: 1px;
  background: linear-gradient(
    to bottom,
    transparent 0%,
    rgba(201,168,76,0.2) 20%,
    rgba(201,168,76,0.2) 80%,
    transparent 100%
  );
  flex-shrink: 0;
}

/* ── RIGHT PANEL ──────────────────────────────── */
.right {
  flex: 0 0 480px;
  background: var(--cream);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 52px;
  position: relative;
  overflow: hidden;
}

/* Subtle texture on right panel */
.right::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 80% 10%, rgba(201,168,76,0.06) 0%, transparent 50%),
                    radial-gradient(circle at 20% 90%, rgba(12,30,62,0.04) 0%, transparent 50%);
  pointer-events: none;
}

/* Top gold accent bar */
.right::after {
  content: '';
  position: absolute;
  top: 0; left: 52px; right: 52px;
  height: 3px;
  background: linear-gradient(90deg, transparent, var(--gold), transparent);
  opacity: 0.5;
}

/* ── LOGIN CARD ───────────────────────────────── */
.login-card {
  width: 100%;
  max-width: 360px;
  position: relative;
  z-index: 1;
  animation: fadeSlideUp 0.8s 0.15s ease both;
}

/* Greeting */
.greeting {
  margin-bottom: 36px;
}

.greeting-eyebrow {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.greeting-eyebrow::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--gold);
  opacity: 0.3;
}

.greeting h2 {
  font-family: 'Cormorant Garamond', serif;
  font-size: 34px;
  font-weight: 700;
  color: var(--text-dark);
  letter-spacing: -0.3px;
  line-height: 1.15;
}

.greeting p {
  font-size: 13.5px;
  color: var(--text-light);
  margin-top: 8px;
  font-weight: 300;
  line-height: 1.5;
}

/* ── ERROR ALERT ──────────────────────────────── */
.error-alert {
  background: var(--error-bg);
  border: 1px solid var(--error-border);
  border-left: 3px solid var(--error-text);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  margin-bottom: 20px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  animation: shake 0.4s ease;
}

.error-icon {
  font-size: 15px;
  flex-shrink: 0;
  margin-top: 1px;
}

.error-alert p {
  font-size: 13px;
  color: var(--error-text);
  font-weight: 500;
  line-height: 1.4;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20%       { transform: translateX(-5px); }
  40%       { transform: translateX(5px); }
  60%       { transform: translateX(-3px); }
  80%       { transform: translateX(3px); }
}

/* ── FORM ─────────────────────────────────────── */
.form-fields { display: flex; flex-direction: column; gap: 18px; }

.field-group { display: flex; flex-direction: column; gap: 7px; }

.field-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-dark);
  letter-spacing: 1.2px;
  text-transform: uppercase;
}

.field-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.field-icon {
  position: absolute;
  left: 14px;
  font-size: 15px;
  pointer-events: none;
  opacity: 0.4;
  transition: opacity var(--transition);
}

.field-wrap input {
  width: 100%;
  padding: 13px 42px 13px 42px;
  border: 1.5px solid var(--input-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-family: 'Outfit', sans-serif;
  font-weight: 400;
  color: var(--text-dark);
  background: white;
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition);
  letter-spacing: 0.2px;
}

.field-wrap input::placeholder { color: #b0bbc8; font-weight: 300; }

.field-wrap input:focus {
  border-color: var(--input-focus);
  box-shadow: 0 0 0 3px rgba(30,61,110,0.08);
}

.field-wrap input:focus ~ .field-icon,
.field-wrap:focus-within .field-icon { opacity: 0.7; }

/* Password toggle */
.toggle-pwd {
  position: absolute;
  right: 14px;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 15px;
  opacity: 0.35;
  padding: 4px;
  line-height: 1;
  transition: opacity var(--transition);
  color: var(--text-dark);
}
.toggle-pwd:hover { opacity: 0.7; }

/* ── SUBMIT BUTTON ────────────────────────────── */
.submit-btn {
  margin-top: 6px;
  width: 100%;
  padding: 15px 24px;
  background: var(--navy);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  font-family: 'Outfit', sans-serif;
  font-size: 13.5px;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: background var(--transition), box-shadow var(--transition), transform 0.15s ease;
  box-shadow: var(--shadow-btn);
}

.submit-btn::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.06) 0%, transparent 60%);
  pointer-events: none;
}

/* Gold shimmer sweep on hover */
.submit-btn::after {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 60%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(201,168,76,0.15), transparent);
  transition: left 0.5s ease;
  pointer-events: none;
}
.submit-btn:hover::after { left: 160%; }

.submit-btn:hover {
  background: var(--navy-light);
  box-shadow: 0 8px 32px rgba(12,30,62,0.45);
  transform: translateY(-1px);
}

.submit-btn:active {
  transform: translateY(0);
  box-shadow: var(--shadow-btn);
}

.btn-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  position: relative;
  z-index: 1;
}

.btn-arrow {
  font-size: 16px;
  transition: transform 0.25s ease;
}
.submit-btn:hover .btn-arrow { transform: translateX(4px); }

/* ── DEFAULT CREDENTIAL HINT ──────────────────── */
.hint-box {
  margin-top: 24px;
  padding: 12px 16px;
  background: white;
  border: 1px solid var(--input-border);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  gap: 12px;
}

.hint-key-icon {
  font-size: 18px;
  flex-shrink: 0;
  opacity: 0.5;
}

.hint-text {
  font-size: 11.5px;
  color: var(--text-light);
  line-height: 1.5;
  font-weight: 300;
}

.hint-text strong {
  color: var(--text-mid);
  font-weight: 600;
  font-family: 'Outfit', monospace;
  background: var(--gold-pale);
  padding: 1px 5px;
  border-radius: 4px;
  letter-spacing: 0.3px;
}

/* ── COPYRIGHT ────────────────────────────────── */
.copyright {
  position: absolute;
  bottom: 28px;
  left: 0; right: 0;
  text-align: center;
  font-size: 11px;
  color: rgba(255,255,255,0.2);
  letter-spacing: 0.5px;
  font-weight: 300;
}

/* ── ANIMATIONS ───────────────────────────────── */
@keyframes fadeSlideDown {
  from { opacity: 0; transform: translateY(-20px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes fadeSlideUp {
  from { opacity: 0; transform: translateY(24px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── RESPONSIVE ───────────────────────────────── */
@media (max-width: 900px) {
  html, body { overflow: auto; }
  .page { flex-direction: column; height: auto; min-height: 100vh; }
  .left {
    flex: none;
    padding: 40px 32px 48px;
    min-height: 340px;
  }
  .left-stats { display: none; }
  .divider { display: none; }
  .right {
    flex: none;
    padding: 48px 28px 56px;
  }
}

@media (max-width: 480px) {
  .left { padding: 32px 24px 40px; }
  .left-headline { font-size: 30px; }
  .right { padding: 40px 20px 48px; }
}
</style>
</head>
<body>

<div class="page">

  <!-- ══════════════ LEFT PANEL ══════════════ -->
  <div class="left">
    <div class="left-bg">
      <div class="orb orb-1"></div>
      <div class="orb orb-2"></div>
      <div class="orb orb-3"></div>
      <div class="arc-line"></div>
      <div class="arc-line arc-line-2"></div>
    </div>

    <!-- Brand -->
    <div class="left-header">
      <div class="logo-wrap">
        <img src="logo.jpg" alt="Asian College Logo">
      </div>
      <div class="brand-name">
        Asian College
        <span>Dumaguete City</span>
      </div>
    </div>

    <!-- Hero -->
    <div class="left-hero">
      <div class="left-eyebrow">Admin Portal</div>
      <h1 class="left-headline">
        School Locker<br>
        <em>Management</em><br>
        System
      </h1>
      <p class="left-desc">
        A centralized platform for tracking locker assignments,
        availability, maintenance, and student records — all in one place.
      </p>
    </div>

    <!-- Stats -->
    <div class="left-stats">
      <div class="stat-item">
        <div class="stat-num">Est.</div>
        <div class="stat-label">1972</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">100%</div>
        <div class="stat-label">Digital Records</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">24 / 7</div>
        <div class="stat-label">System Access</div>
      </div>
    </div>

    <div class="copyright">© <?= date('Y') ?> Asian College · Locker Management System</div>
  </div>

  <!-- Vertical divider -->
  <div class="divider"></div>

  <!-- ══════════════ RIGHT PANEL ══════════════ -->
  <div class="right">
    <div class="login-card">

      <div class="greeting">
        <div class="greeting-eyebrow">Secure Access</div>
        <h2>Welcome Back</h2>
        <p>Sign in with your administrator credentials to continue.</p>
      </div>

      <?php if ($error): ?>
      <div class="error-alert">
        <span class="error-icon">⚠️</span>
        <p><?= htmlspecialchars($error) ?></p>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <div class="form-fields">

          <div class="field-group">
            <label class="field-label" for="username">Username</label>
            <div class="field-wrap">
              <span class="field-icon">👤</span>
              <input
                type="text"
                id="username"
                name="username"
                placeholder="Enter your username"
                value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                required
                autofocus
              >
            </div>
          </div>

          <div class="field-group">
            <label class="field-label" for="password">Password</label>
            <div class="field-wrap">
              <span class="field-icon">🔑</span>
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                required
              >
              <button type="button" class="toggle-pwd" id="togglePwd" title="Show / hide password">
                👁
              </button>
            </div>
          </div>

        </div>

        <div style="margin-top: 28px; display: flex; flex-direction: column; gap: 0;">
          <button type="submit" class="submit-btn">
            <span class="btn-inner">
              Sign In
              <span class="btn-arrow">→</span>
            </span>
          </button>
        </div>
      </form>

      <!-- Default credentials hint -->
      <div class="hint-box">
        <span class="hint-key-icon">🔐</span>
        <div class="hint-text">
          Default credentials &nbsp;·&nbsp;
          Username: <strong>admin</strong>
          &nbsp;&nbsp;
          Password: <strong>admin123</strong>
        </div>
      </div>

    </div>
  </div>

</div><!-- /page -->

<script>
// ── Password visibility toggle ──
const toggleBtn = document.getElementById('togglePwd');
const pwdInput  = document.getElementById('password');
let   visible   = false;

toggleBtn.addEventListener('click', () => {
  visible = !visible;
  pwdInput.type       = visible ? 'text' : 'password';
  toggleBtn.textContent = visible ? '🙈' : '👁';
  pwdInput.focus();
});

// ── Subtle submit button loading state ──
document.querySelector('form').addEventListener('submit', function() {
  const btn = this.querySelector('.submit-btn');
  btn.innerHTML = '<span class="btn-inner">Signing In…</span>';
  btn.style.opacity = '0.8';
  btn.disabled = true;
});
</script>

</body>
</html>