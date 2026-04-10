<?php
session_start();
require 'db.php';

$current_page  = 'students';
$page_title    = 'Students';
$page_subtitle = 'Manage student records';

// ── HANDLE ACTIONS ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $sid   = trim($_POST['student_id'] ?? '');
        $name  = trim($_POST['full_name'] ?? '');
        $grade = trim($_POST['grade_section'] ?? '');
        $cont  = trim($_POST['contact'] ?? '');

        if (!$sid || !$name) {
            $_SESSION['toast_msg']  = 'Student ID and Full Name are required.';
            $_SESSION['toast_type'] = 'error';
        } elseif (db_col($pdo, "SELECT COUNT(*) FROM students WHERE student_id=?", [$sid]) > 0) {
            $_SESSION['toast_msg']  = 'Student ID already exists.';
            $_SESSION['toast_type'] = 'error';
        } else {
            db_run($pdo, "INSERT INTO students (student_id,full_name,grade_section,contact) VALUES(?,?,?,?)",
                [$sid, $name, $grade, $cont]);
            $_SESSION['toast_msg']  = 'Student added successfully!';
            $_SESSION['toast_type'] = 'success';
        }
    }

    if ($act === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['full_name'] ?? '');
        $grade = trim($_POST['grade_section'] ?? '');
        $cont  = trim($_POST['contact'] ?? '');

        if (!$name) {
            $_SESSION['toast_msg']  = 'Full Name is required.';
            $_SESSION['toast_type'] = 'error';
        } else {
            db_run($pdo, "UPDATE students SET full_name=?,grade_section=?,contact=? WHERE id=?",
                [$name, $grade, $cont, $id]);
            $_SESSION['toast_msg']  = 'Student updated successfully!';
            $_SESSION['toast_type'] = 'success';
        }
    }

    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $active = db_col($pdo,
            "SELECT COUNT(*) FROM locker_assignments WHERE student_id=? AND date_returned IS NULL", [$id]);
        if ($active > 0) {
            $_SESSION['toast_msg']  = 'Cannot delete: student has an active locker assignment.';
            $_SESSION['toast_type'] = 'error';
        } else {
            db_run($pdo, "DELETE FROM students WHERE id=?", [$id]);
            $_SESSION['toast_msg']  = 'Student deleted.';
            $_SESSION['toast_type'] = 'success';
        }
    }

    header("Location: students.php"); exit;
}

// ── LOAD DATA ───────────────────────────────────────
$search   = trim($_GET['q'] ?? '');
$edit_id  = (int)($_GET['edit'] ?? 0);
$edit_row = $edit_id ? db_one($pdo, "SELECT * FROM students WHERE id=?", [$edit_id]) : null;

$students = $search
    ? db_all($pdo,
        "SELECT * FROM students
         WHERE full_name LIKE ? OR student_id LIKE ? OR grade_section LIKE ?
         ORDER BY full_name",
        ["%$search%", "%$search%", "%$search%"])
    : db_all($pdo, "SELECT * FROM students ORDER BY full_name");

include 'header.php';
?>

<!-- ADD / EDIT FORM BOX -->
<div class="form-box">
  <div class="form-box-title">
    <?= $edit_row ? '✏️ Edit Student' : '➕ Add New Student' ?>
  </div>
  <form method="POST" action="students.php">
    <input type="hidden" name="action" value="<?= $edit_row ? 'edit' : 'add' ?>">
    <?php if ($edit_row): ?>
      <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
    <?php endif; ?>

    <div class="form-grid form-grid-<?= $edit_row ? '3' : '4' ?>">
      <?php if (!$edit_row): ?>
      <div class="form-group">
        <label>Student ID *</label>
        <input type="text" name="student_id" placeholder="e.g. 2024-0001" required>
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name"
               value="<?= e($edit_row['full_name'] ?? '') ?>"
               placeholder=""
               required>
      </div>
      <div class="form-group">
        <label>Grade &amp; Section</label>
        <input type="text" name="grade_section"
               value="<?= e($edit_row['grade_section'] ?? '') ?>"
               placeholder="e.g. Grade 10 – A">
      </div>
      <div class="form-group">
        <label>Contact</label>
        <input type="text" name="contact"
               value="<?= e($edit_row['contact'] ?? '') ?>"
               placeholder="Phone number">
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <?= $edit_row ? '💾 Save Changes' : '➕ Add Student' ?>
      </button>
      <?php if ($edit_row): ?>
        <a href="students.php" class="btn btn-ghost">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- STUDENTS TABLE -->
<div class="table-box">
  <div class="table-header">
    <h3>All Students <span class="count-badge"><?= count($students) ?></span></h3>
    <div class="search-bar">
      <form method="GET" action="students.php" style="display:contents">
        <div class="search-input-wrap">
          <input type="text" name="q" placeholder="Search students…"
                 value="<?= e($search) ?>" onchange="this.form.submit()">
        </div>
      </form>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Student ID</th>
        <th>Full Name</th>
        <th>Grade &amp; Section</th>
        <th>Contact</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($students)): ?>
        <tr class="empty-row"><td colspan="6">No students found</td></tr>
      <?php else: ?>
        <?php foreach ($students as $i => $s): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><span class="student-id-chip"><?= e($s['student_id']) ?></span></td>
          <td><strong><?= e($s['full_name']) ?></strong></td>
          <td><?= e($s['grade_section']) ?: '—' ?></td>
          <td><?= e($s['contact']) ?: '—' ?></td>
          <td>
            <a href="students.php?edit=<?= $s['id'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
            <form method="POST" action="students.php" style="display:inline"
                  onsubmit="return confirm('Delete this student? This cannot be undone.')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" style="margin-left:6px">🗑️</button>
            </form>
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