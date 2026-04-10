<?php
session_start();
require 'db.php';

$current_page  = 'maintenance';
$page_title    = 'Maintenance';
$page_subtitle = 'Track and manage lockers under repair';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'send') {
        $lid    = (int)($_POST['locker_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (!$lid) {
            $_SESSION['toast_msg']  = 'Please select a locker.';
            $_SESSION['toast_type'] = 'error';
        } else {
            $locker = db_one($pdo, "SELECT * FROM lockers WHERE id=?", [$lid]);
            if (!$locker) {
                $_SESSION['toast_msg']  = 'Locker not found.';
                $_SESSION['toast_type'] = 'error';
            } elseif ($locker['status'] === 'occupied') {
                $_SESSION['toast_msg']  = 'Cannot send an occupied locker to maintenance. Return it first.';
                $_SESSION['toast_type'] = 'error';
            } else {
                db_run($pdo,
                    "UPDATE lockers SET status='maintenance', maint_reason=? WHERE id=?",
                    [$reason ?: 'General maintenance', $lid]);
                $_SESSION['toast_msg']  = 'Locker #' . $locker['locker_number'] . ' sent to maintenance.';
                $_SESSION['toast_type'] = 'success';
            }
        }
    }

    if ($act === 'restore') {
        $lid = (int)($_POST['locker_id'] ?? 0);
        $locker = db_one($pdo, "SELECT * FROM lockers WHERE id=?", [$lid]);
        if ($locker) {
            db_run($pdo, "UPDATE lockers SET status='available', maint_reason=NULL WHERE id=?", [$lid]);
            $_SESSION['toast_msg']  = 'Locker #' . $locker['locker_number'] . ' is now available!';
            $_SESSION['toast_type'] = 'success';
        }
    }

    header("Location: maintenance.php"); exit;
}

$avail_lockers = db_all($pdo, "SELECT * FROM lockers WHERE status='available' ORDER BY locker_number");
$maint_lockers = db_all($pdo, "SELECT * FROM lockers WHERE status='maintenance' ORDER BY locker_number");

include 'header.php';
?>

<div class="form-box">
  <div class="form-box-title">🔧 Send Locker to Maintenance</div>
  <form method="POST" action="maintenance.php" id="maint-form">
    <input type="hidden" name="action" value="send">
    <div class="form-grid form-grid-2">
      <div class="form-group">
        <label>Locker (Available Only) *</label>
        <select name="locker_id" id="locker_select" required>
          <option value="">-- Select Locker --</option>
          <?php foreach ($avail_lockers as $l): ?>
          <option value="<?= $l['id'] ?>" data-number="<?= e($l['locker_number']) ?>">
            <?= e($l['locker_number']) ?><?= $l['location'] ? ' (' . e($l['location']) . ')' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($avail_lockers)): ?>
          <small style="color:var(--text-muted);font-size:12px;margin-top:4px;display:block;">
            ℹ️ No available lockers to send to maintenance.
          </small>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Reason / Issue *</label>
        <input type="text" name="reason" id="reason_input" placeholder="e.g. Broken lock, needs repair" required>
      </div>
    </div>
    <div class="form-actions">
      <button type="button" class="btn btn-primary" onclick="confirmMaint()" style="background:#b07d10;">
        🔧 Send to Maintenance
      </button>
    </div>
  </form>
</div>

<div class="table-box">
  <div class="table-header">
    <h3>🔧 Lockers Under Maintenance <span class="count-badge"><?= count($maint_lockers) ?></span></h3>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>Locker #</th><th>Location</th><th>Issue / Reason</th><th>Status</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($maint_lockers)): ?>
        <tr class="empty-row"><td colspan="6">No lockers currently under maintenance</td></tr>
      <?php else: ?>
        <?php foreach ($maint_lockers as $i => $l): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><strong><?= e($l['locker_number']) ?></strong></td>
          <td><?= e($l['location']) ?: '—' ?></td>
          <td><span style="color:var(--warning);font-weight:500;">⚠️ <?= e($l['maint_reason'] ?? 'General maintenance') ?></span></td>
          <td><span class="badge badge-maintenance">Under Maintenance</span></td>
          <td>
            <form method="POST" action="maintenance.php" style="display:inline"
                  onsubmit="return confirm('Mark locker <?= e($l['locker_number']) ?> as available? Make sure repairs are completed.')">
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="locker_id" value="<?= $l['id'] ?>">
              <button type="submit" class="btn btn-success btn-sm">✅ Mark Available</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- CONFIRMATION MODAL -->
<div class="modal-overlay" id="maintModal">
  <div class="modal">
    <div class="modal-title">🔧 Confirm Maintenance Request</div>
    <p style="color:var(--text-muted);font-size:14px;margin-bottom:16px;">
      You are about to mark this locker as <strong style="color:var(--warning)">Under Maintenance</strong> and make it unavailable for assignment:
    </p>
    <div style="background:var(--warning-bg);border:1px solid #e6c97a;border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
      <div style="font-size:14px;color:var(--text);line-height:2;">
        <strong>Locker:</strong> <span id="modal_locker_num" style="color:var(--navy-dark);font-weight:700;"></span><br>
        <strong>Issue:</strong> <span id="modal_reason" style="color:var(--warning);"></span>
      </div>
    </div>
    <p style="font-size:13px;color:var(--text-muted);">
      The locker status will be updated to <em>Under Maintenance</em> and cannot be assigned to students until you mark it as available again.
    </p>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button type="button" onclick="submitMaint()" class="btn" style="background:var(--warning);color:white;font-weight:700;">
        🔧 Yes, Send to Maintenance
      </button>
    </div>
  </div>
</div>

<script>
function confirmMaint() {
  const select = document.getElementById('locker_select');
  const reason = document.getElementById('reason_input').value.trim();
  if (!select.value) { alert('Please select a locker first.'); return; }
  if (!reason) { alert('Please enter a reason/issue for maintenance.'); document.getElementById('reason_input').focus(); return; }
  document.getElementById('modal_locker_num').textContent = select.options[select.selectedIndex].dataset.number;
  document.getElementById('modal_reason').textContent = reason;
  document.getElementById('maintModal').classList.add('show');
}
function closeModal() { document.getElementById('maintModal').classList.remove('show'); }
function submitMaint() { document.getElementById('maint-form').submit(); }
document.getElementById('maintModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
</script>

</div></body></html>