<?php
session_start();
require 'db.php';

$current_page  = 'dashboard';
$page_title    = 'Dashboard';
$page_subtitle = 'Overview of the locker management system';

// Stats
$total_students    = db_col($pdo, "SELECT COUNT(*) FROM students");
$total_lockers     = db_col($pdo, "SELECT COUNT(*) FROM lockers");
$total_available   = db_col($pdo, "SELECT COUNT(*) FROM lockers WHERE status='available'");
$total_occupied    = db_col($pdo, "SELECT COUNT(*) FROM lockers WHERE status='occupied'");
$total_maintenance = db_col($pdo, "SELECT COUNT(*) FROM lockers WHERE status='maintenance'");

// Recent 10 active assignments
$recent = db_all($pdo, "
    SELECT la.date_assigned, s.student_id AS sid, s.full_name, l.locker_number
    FROM locker_assignments la
    JOIN students s ON la.student_id = s.id
    JOIN lockers  l ON la.locker_id  = l.id
    WHERE la.date_returned IS NULL
    ORDER BY la.id DESC
    LIMIT 10
");

include 'header.php';
?>

<!-- STAT CARDS -->
<div class="cards-grid">
  <div class="stat-card">
    <div class="stat-icon">👨‍🎓</div>
    <div class="stat-num"><?= $total_students ?></div>
    <div class="stat-label">Total Students</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon">🗄️</div>
    <div class="stat-num"><?= $total_lockers ?></div>
    <div class="stat-label">Total Lockers</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-num"><?= $total_available ?></div>
    <div class="stat-label">Available</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">🔒</div>
    <div class="stat-num"><?= $total_occupied ?></div>
    <div class="stat-label">Occupied</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">🔧</div>
    <div class="stat-num"><?= $total_maintenance ?></div>
    <div class="stat-label">Maintenance</div>
  </div>
</div>

<!-- RECENT ASSIGNMENTS TABLE -->
<div class="table-box">
  <div class="table-header">
    <h3>Recent Assignments <span class="count-badge">Last 10</span></h3>
    <a href="assignments.php" class="btn btn-primary btn-sm">View All</a>
  </div>
  <table>
    <thead>
      <tr>
        <th>Student ID</th>
        <th>Student Name</th>
        <th>Locker #</th>
        <th>Date Assigned</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($recent)): ?>
        <tr class="empty-row"><td colspan="4">No active assignments yet</td></tr>
      <?php else: ?>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td><span class="student-id-chip"><?= e($r['sid']) ?></span></td>
          <td><?= e($r['full_name']) ?></td>
          <td><strong><?= e($r['locker_number']) ?></strong></td>
          <td><?= e($r['date_assigned']) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</div><!-- /main -->
</body>
</html>