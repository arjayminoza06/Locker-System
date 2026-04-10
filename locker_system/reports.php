<?php
session_start();
require 'db.php';

$current_page  = 'reports';
$page_title    = 'Reports';
$page_subtitle = 'Locker usage overview and history';

// ── LOAD DATA ───────────────────────────────────────

// Available lockers
$available = db_all($pdo,
    "SELECT * FROM lockers WHERE status='available' ORDER BY locker_number");

// Occupied lockers with student info
$occupied = db_all($pdo, "
    SELECT l.locker_number, l.location,
           s.student_id AS sid, s.full_name,
           la.date_assigned
    FROM lockers l
    JOIN locker_assignments la ON l.id = la.locker_id
    JOIN students s            ON la.student_id = s.id
    WHERE l.status = 'occupied'
      AND la.date_returned IS NULL
    ORDER BY l.locker_number
");

// Full assignment history
$history = db_all($pdo, "
    SELECT la.date_assigned, la.date_returned,
           s.full_name, s.student_id AS sid,
           l.locker_number
    FROM locker_assignments la
    JOIN students s ON la.student_id = s.id
    JOIN lockers  l ON la.locker_id  = l.id
    ORDER BY la.id DESC
");

include 'header.php';
?>

<!-- AVAILABLE LOCKERS -->
<div class="table-box">
  <div class="table-header">
    <h3 style="color:var(--success)">
      ✅ Available Lockers
      <span class="count-badge"><?= count($available) ?></span>
    </h3>
  </div>
  <table>
    <thead>
      <tr><th>#</th><th>Locker Number</th><th>Location</th></tr>
    </thead>
    <tbody>
      <?php if (empty($available)): ?>
        <tr class="empty-row"><td colspan="3">No available lockers</td></tr>
      <?php else: ?>
        <?php foreach ($available as $i => $l): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><strong><?= e($l['locker_number']) ?></strong></td>
          <td><?= e($l['location']) ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- OCCUPIED LOCKERS -->
<div class="table-box">
  <div class="table-header">
    <h3 style="color:#1a5276">
      🔒 Occupied Lockers
      <span class="count-badge"><?= count($occupied) ?></span>
    </h3>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Locker #</th>
        <th>Student ID</th>
        <th>Assigned To</th>
        <th>Date Assigned</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($occupied)): ?>
        <tr class="empty-row"><td colspan="5">No occupied lockers</td></tr>
      <?php else: ?>
        <?php foreach ($occupied as $i => $o): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><strong><?= e($o['locker_number']) ?></strong></td>
          <td><span class="student-id-chip"><?= e($o['sid']) ?></span></td>
          <td><?= e($o['full_name']) ?></td>
          <td><?= e($o['date_assigned']) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- FULL HISTORY -->
<div class="table-box">
  <div class="table-header">
    <h3>
      📜 Full Assignment History
      <span class="count-badge"><?= count($history) ?></span>
    </h3>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Locker #</th>
        <th>Student</th>
        <th>Date Assigned</th>
        <th>Date Returned</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($history)): ?>
        <tr class="empty-row"><td colspan="6">No assignment history</td></tr>
      <?php else: ?>
        <?php foreach ($history as $i => $h): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><strong><?= e($h['locker_number']) ?></strong></td>
          <td><?= e($h['full_name']) ?></td>
          <td><?= e($h['date_assigned']) ?></td>
          <td><?= $h['date_returned'] ? e($h['date_returned']) : '—' ?></td>
          <td>
            <?php if ($h['date_returned']): ?>
              <span class="badge badge-returned">Returned</span>
            <?php else: ?>
              <span class="badge badge-active">Active</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</div><!-- /main -->
</body>
</html>