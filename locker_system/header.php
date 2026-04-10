<?php
// header.php — shared top of every app page
// Requires: $current_page (string), $_SESSION['username']
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$username      = $_SESSION['username'] ?? 'admin';
$avatar_letter = strtoupper($username[0]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Asian College – School Locker Management System</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="responsive.css">
</head>
<body>

<!-- ① Sidebar overlay (click to close on mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar">
  <div class="sidebar-brand">
    <img src="logo.jpg" alt="Asian College Logo" style="width:48px;height:48px;object-fit:contain;border-radius:8px;background:white;padding:3px;flex-shrink:0;">
    <div class="brand-text">
      <h2>Asian College</h2>
      <span>Locker System</span>
    </div>
  </div>

  <nav>
    <div class="sidebar-section-label">Main Menu</div>
    <a href="dashboard.php"   <?= ($current_page==='dashboard')   ? 'class="active"' : '' ?>>
      <span class="nav-icon">📊</span> Dashboard
    </a>
    <a href="students.php"    <?= ($current_page==='students')    ? 'class="active"' : '' ?>>
      <span class="nav-icon">👨‍🎓</span> Students
    </a>
    <a href="lockers.php"     <?= ($current_page==='lockers')     ? 'class="active"' : '' ?>>
      <span class="nav-icon">🔐</span> Lockers
    </a>
    <a href="assignments.php" <?= ($current_page==='assignments') ? 'class="active"' : '' ?>>
      <span class="nav-icon">🔁</span> Assignments
    </a>
    <div class="sidebar-section-label">Management</div>
    <a href="buildings.php"   <?= ($current_page==='buildings')   ? 'class="active"' : '' ?>>
      <span class="nav-icon">🏢</span> Buildings
    </a>
    <a href="maintenance.php" <?= ($current_page==='maintenance') ? 'class="active"' : '' ?>>
      <span class="nav-icon">🔧</span> Maintenance
    </a>
    <a href="reports.php"     <?= ($current_page==='reports')     ? 'class="active"' : '' ?>>
      <span class="nav-icon">📋</span> Reports
    </a>
  </nav>

  <div class="sidebar-footer">
    Logged in as <strong><?= htmlspecialchars($username) ?></strong><br>
    <a href="logout.php">⬅ Logout</a>
  </div>
</div>

<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">

    <!-- ② Hamburger button (visible on mobile only via CSS) -->
    <button class="hamburger" id="hamburgerBtn" aria-label="Open navigation menu">
      <span></span>
      <span></span>
      <span></span>
    </button>

    <div class="topbar-left">
      <h1><?= $page_title ?? 'Dashboard' ?></h1>
      <p><?= $page_subtitle ?? '' ?></p>
    </div>

    <div class="user-chip">
      <div class="user-avatar"><?= $avatar_letter ?></div>
      <span><?= htmlspecialchars($username) ?></span>
    </div>
  </div>

  <!-- TOAST (session flash messages) -->
  <?php if (!empty($_SESSION['toast_msg'])): ?>
  <div class="toast <?= $_SESSION['toast_type'] === 'error' ? 'error' : 'success' ?> show" id="toast">
    <?= htmlspecialchars($_SESSION['toast_msg']) ?>
  </div>
  <script>
    setTimeout(() => {
      const t = document.getElementById('toast');
      if (t) t.style.display = 'none';
    }, 3500);
  </script>
  <?php
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
  endif;
  ?>

  <!-- ③ Responsive JS loaded after DOM is ready -->
  <script src="responsive.js"></script>