<?php
session_start();
require 'db.php';

$current_page  = 'assignments';
$page_title    = 'Assignments';
$page_subtitle = 'Assign lockers to students';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'assign') {
        $sid      = (int)($_POST['student_id'] ?? 0);
        $lid      = (int)($_POST['locker_id'] ?? 0);
        $date     = trim($_POST['date_assigned'] ?? date('Y-m-d'));
        $duration = (int)($_POST['rental_duration'] ?? 6); // 6 or 12 months

        if (!$sid || !$lid || !$date) {
            $_SESSION['toast_msg']  = 'Please fill in all fields.';
            $_SESSION['toast_type'] = 'error';
        } elseif (db_col($pdo,
            "SELECT COUNT(*) FROM locker_assignments WHERE locker_id=? AND date_returned IS NULL",
            [$lid]) > 0) {
            // RULE: One person per locker at a time — block if ANY active (unreturned) assignment exists
            $_SESSION['toast_msg']  = 'This locker is currently occupied. It can only be reassigned after it is returned or the rental expires.';
            $_SESSION['toast_type'] = 'error';
        } else {
            // Calculate expiry date
            $expiry = date('Y-m-d', strtotime($date . " +{$duration} months"));
            db_run($pdo,
                "INSERT INTO locker_assignments (student_id, locker_id, date_assigned, rental_duration, expiry_date) VALUES(?,?,?,?,?)",
                [$sid, $lid, $date, $duration, $expiry]);
            db_run($pdo, "UPDATE lockers SET status='occupied' WHERE id=?", [$lid]);
            $student = db_one($pdo, "SELECT full_name FROM students WHERE id=?", [$sid]);
            $locker  = db_one($pdo, "SELECT locker_number FROM lockers WHERE id=?", [$lid]);
            $_SESSION['toast_msg']  = 'Locker ' . $locker['locker_number'] . ' assigned to ' . $student['full_name'] . ' until ' . $expiry . '!';
            $_SESSION['toast_type'] = 'success';
        }
    }

    if ($act === 'return') {
        $aid = (int)($_POST['assignment_id'] ?? 0);
        $row = db_one($pdo, "SELECT * FROM locker_assignments WHERE id=?", [$aid]);
        if ($row && !$row['date_returned']) {
            db_run($pdo, "UPDATE locker_assignments SET date_returned=CURDATE() WHERE id=?", [$aid]);
            db_run($pdo, "UPDATE lockers SET status='available' WHERE id=?", [$row['locker_id']]);
            $_SESSION['toast_msg']  = 'Locker returned successfully!';
            $_SESSION['toast_type'] = 'success';
        }
    }

    header("Location: assignments.php"); exit;
}

// Auto-expire: mark expired assignments as returned and free up lockers
$expired = db_all($pdo,
    "SELECT * FROM locker_assignments WHERE date_returned IS NULL AND expiry_date IS NOT NULL AND expiry_date < CURDATE()");
foreach ($expired as $exp) {
    db_run($pdo, "UPDATE locker_assignments SET date_returned=expiry_date WHERE id=?", [$exp['id']]);
    db_run($pdo, "UPDATE lockers SET status='available' WHERE id=?", [$exp['locker_id']]);
}

// Active assignments
$active = db_all($pdo, "
    SELECT la.id AS aid, la.date_assigned, la.rental_duration, la.expiry_date,
           DATEDIFF(IFNULL(la.expiry_date, DATE_ADD(la.date_assigned, INTERVAL 6 MONTH)), CURDATE()) AS days_left,
           s.id AS sid, s.student_id, s.full_name,
           l.id AS lid, l.locker_number, l.location,
           (SELECT COUNT(*) FROM locker_assignments la2
            WHERE la2.student_id = s.id AND la2.date_returned IS NULL) AS student_locker_count
    FROM locker_assignments la
    JOIN students s ON la.student_id = s.id
    JOIN lockers  l ON la.locker_id  = l.id
    WHERE la.date_returned IS NULL
    ORDER BY s.full_name, la.date_assigned DESC
");

$all_students      = db_all($pdo, "SELECT * FROM students ORDER BY full_name");
$available_lockers = db_all($pdo, "SELECT * FROM lockers WHERE status='available' ORDER BY locker_number");

include 'header.php';
?>

<div class="form-box">
  <div class="form-box-title">🔁 Assign Locker to Student</div>
  <form method="POST" action="assignments.php" id="assign-form">
    <input type="hidden" name="action" value="assign">
    <div class="form-grid form-grid-3">
      <div class="form-group" style="position:relative;">
        <label>Student ID *</label>
        <div class="student-search-wrap">
          <input type="text"
                 id="student_id_search"
                 placeholder="Type Student ID (e.g. 2024-0001)…"
                 autocomplete="off"
                 required>
          <div class="student-suggestions" id="student_suggestions"></div>
        </div>
        <!-- Hidden fields sent with form -->
        <input type="hidden" name="student_id" id="student_id_hidden">
        <div class="student-selected-chip" id="student_selected_chip" style="display:none;"></div>
      </div>
      <div class="form-group">
        <label>Locker *</label>
        <select name="locker_id" required>
          <option value="">-- Select Locker --</option>
          <?php foreach ($available_lockers as $l): ?>
          <option value="<?= $l['id'] ?>">
            <?= e($l['locker_number']) ?><?= $l['location'] ? ' (' . e($l['location']) . ')' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Date Assigned *</label>
        <input type="date" name="date_assigned" id="date_assigned" value="<?= date('Y-m-d') ?>" required onchange="updateExpiry()">
      </div>
      <div class="form-group">
        <label>Rental Duration *</label>
        <select name="rental_duration" id="rental_duration" onchange="updateExpiry()">
          <option value="6">6 Months (1 Semester)</option>
          <option value="12">12 Months (Full Year)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Expiration Date (auto-calculated)</label>
        <input type="text" id="expiry_preview" readonly
               style="background:#f0f4ff;color:var(--navy);font-weight:700;cursor:default;"
               placeholder="Will be calculated automatically">
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Assign Locker</button>
    </div>
  </form>
</div>

<!-- ACTIVE ASSIGNMENTS TABLE -->
<div class="table-box" id="active-assignments-table">
  <div class="table-header">
    <h3>Active Assignments <span class="count-badge"><?= count($active) ?></span></h3>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Student ID</th>
        <th>Student Name</th>
        <th>Lockers Held</th>
        <th>Locker #</th>
        <th>Location</th>
        <th>Date Assigned</th>
        <th>Duration</th>
        <th>Expiry Date</th>
        <th>Days Left</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($active)): ?>
        <tr class="empty-row"><td colspan="11">No active assignments</td></tr>
      <?php else: ?>
        <?php foreach ($active as $i => $a):
          $days = (int)$a['days_left'];
          if ($days <= 7)       { $days_class = 'color:#C0392B;font-weight:700;'; $days_icon = '🔴'; }
          elseif ($days <= 30)  { $days_class = 'color:var(--warning);font-weight:700;'; $days_icon = '🟡'; }
          else                  { $days_class = 'color:var(--success);font-weight:600;'; $days_icon = '🟢'; }
        ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><span class="student-id-chip"><?= e($a['student_id']) ?></span></td>
          <td><?= e($a['full_name']) ?></td>
          <td>
            <?php if ($a['student_locker_count'] > 1): ?>
              <span class="badge badge-occupied"><?= $a['student_locker_count'] ?> lockers</span>
            <?php else: ?>
              <span style="color:var(--gray);font-size:12px;">1 locker</span>
            <?php endif; ?>
          </td>
          <td><strong><?= e($a['locker_number']) ?></strong></td>
          <td><?= e($a['location']) ?: '—' ?></td>
          <td><?= e($a['date_assigned']) ?></td>
          <td>
            <span class="badge <?= $a['rental_duration'] == 12 ? 'badge-occupied' : 'badge-available' ?>">
              <?= $a['rental_duration'] ?> months
            </span>
          </td>
          <td><strong><?= e($a['expiry_date']) ?></strong></td>
          <td><span style="<?= $days_class ?>"><?= $days_icon ?> <?= $days ?> days</span></td>
          <td>
            <form method="POST" action="assignments.php" style="display:inline"
                  onsubmit="return confirm('Return locker <?= e($a['locker_number']) ?> for <?= e($a['full_name']) ?>?')">
              <input type="hidden" name="action" value="return">
              <input type="hidden" name="assignment_id" value="<?= $a['aid'] ?>">
              <button type="submit" class="btn btn-success btn-sm">↩️ Return</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<style>
/* ── Student Search Autocomplete ── */
.student-search-wrap {
  position: relative;
}
.student-search-wrap input[type="text"] {
  padding: 10px 14px 10px 36px;
  border: 1.5px solid var(--gray-light);
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-family: 'DM Sans', sans-serif;
  color: var(--text);
  background: var(--off-white);
  outline: none;
  transition: border-color 0.2s, background 0.2s;
  width: 100%;
}
.student-search-wrap::before {
  content: '🔍';
  position: absolute;
  left: 10px;
  top: 11px;
  font-size: 13px;
  pointer-events: none;
  z-index: 1;
}
.student-search-wrap input:focus {
  border-color: var(--navy);
  background: white;
}
.student-suggestions {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: white;
  border: 1.5px solid var(--navy);
  border-radius: var(--radius-sm);
  box-shadow: var(--shadow-md);
  z-index: 300;
  max-height: 220px;
  overflow-y: auto;
  display: none;
}
.student-suggestions.open { display: block; }
.suggestion-item {
  padding: 10px 14px;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  border-bottom: 1px solid var(--gray-light);
  transition: background 0.15s;
}
.suggestion-item:last-child { border-bottom: none; }
.suggestion-item:hover,
.suggestion-item.highlighted { background: #f0f4ff; }
.suggestion-sid {
  display: inline-block;
  background: var(--navy-dark);
  color: white;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 5px;
  letter-spacing: 0.4px;
  flex-shrink: 0;
}
.suggestion-name { font-weight: 500; color: var(--text); flex: 1; }
.suggestion-active {
  font-size: 11px;
  background: #fef3cc;
  color: var(--warning);
  border: 1px solid #e6c97a;
  border-radius: 4px;
  padding: 1px 7px;
  font-weight: 700;
  flex-shrink: 0;
}
.suggestion-none {
  padding: 12px 14px;
  font-size: 13px;
  color: var(--text-muted);
  text-align: center;
}
.student-selected-chip {
  margin-top: 8px;
  padding: 9px 14px;
  background: #e8f0fe;
  border: 1.5px solid var(--navy);
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  color: var(--navy-dark);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.student-selected-chip .chip-clear {
  cursor: pointer;
  color: var(--text-muted);
  font-size: 16px;
  line-height: 1;
  padding: 0 2px;
  transition: color 0.15s;
}
.student-selected-chip .chip-clear:hover { color: var(--red); }
</style>

<script>
// ── Build student data from PHP ──
const STUDENTS = <?php
  $js_students = [];
  foreach ($all_students as $s) {
    $activeCount = 0;
    foreach ($active as $a) { if ($a['sid'] == $s['id']) $activeCount++; }
    $js_students[] = [
      'id'      => (int)$s['id'],
      'sid'     => $s['student_id'],
      'name'    => $s['full_name'],
      'active'  => $activeCount,
    ];
  }
  echo json_encode($js_students);
?>;

// ── Autocomplete logic ──
(function() {
  const searchInput  = document.getElementById('student_id_search');
  const hiddenInput  = document.getElementById('student_id_hidden');
  const suggestBox   = document.getElementById('student_suggestions');
  const selectedChip = document.getElementById('student_selected_chip');
  let highlighted    = -1;
  let filteredList   = [];

  function renderSuggestions(list) {
    filteredList = list;
    highlighted  = -1;
    suggestBox.innerHTML = '';
    if (!list.length) {
      suggestBox.innerHTML = '<div class="suggestion-none">No students found</div>';
      suggestBox.classList.add('open');
      return;
    }
    list.forEach((s, i) => {
      const div = document.createElement('div');
      div.className = 'suggestion-item';
      div.dataset.index = i;
      div.innerHTML =
        '<span class="suggestion-sid">' + escHtml(s.sid) + '</span>' +
        '<span class="suggestion-name">' + escHtml(s.name) + '</span>' +
        (s.active > 0
          ? '<span class="suggestion-active">Active (' + s.active + ')</span>'
          : '');
      div.addEventListener('mousedown', function(e) {
        e.preventDefault(); // keep focus on input briefly so blur doesn't fire first
        selectStudent(s);
      });
      suggestBox.appendChild(div);
    });
    suggestBox.classList.add('open');
  }

  function selectStudent(s) {
    hiddenInput.value = s.id;
    searchInput.value = '';
    searchInput.style.display = 'none';
    suggestBox.classList.remove('open');
    selectedChip.style.display = 'flex';
    selectedChip.innerHTML =
      '<span>' +
        '<span class="suggestion-sid" style="margin-right:8px;">' + escHtml(s.sid) + '</span>' +
        escHtml(s.name) +
        (s.active > 0 ? ' <span class="suggestion-active" style="margin-left:6px;">Active (' + s.active + ')</span>' : '') +
      '</span>' +
      '<span class="chip-clear" title="Change student">✕</span>';
    selectedChip.querySelector('.chip-clear').addEventListener('click', clearSelection);
    // Remove required from search input since we have the hidden value
    searchInput.removeAttribute('required');
  }

  function clearSelection() {
    hiddenInput.value = '';
    searchInput.value = '';
    searchInput.style.display = '';
    searchInput.setAttribute('required', '');
    selectedChip.style.display = 'none';
    searchInput.focus();
  }

  function escHtml(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  searchInput.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    if (q.length === 0) { suggestBox.classList.remove('open'); return; }
    const matches = STUDENTS.filter(s =>
      s.sid.toLowerCase().includes(q) || s.name.toLowerCase().includes(q)
    ).slice(0, 10);
    renderSuggestions(matches);
  });

  searchInput.addEventListener('keydown', function(e) {
    const items = suggestBox.querySelectorAll('.suggestion-item');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      highlighted = Math.min(highlighted + 1, items.length - 1);
      items.forEach((el,i) => el.classList.toggle('highlighted', i === highlighted));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      highlighted = Math.max(highlighted - 1, 0);
      items.forEach((el,i) => el.classList.toggle('highlighted', i === highlighted));
    } else if (e.key === 'Enter') {
      if (highlighted >= 0 && filteredList[highlighted]) {
        e.preventDefault();
        selectStudent(filteredList[highlighted]);
      }
    } else if (e.key === 'Escape') {
      suggestBox.classList.remove('open');
    }
  });

  document.addEventListener('click', function(e) {
    if (!e.target.closest('.student-search-wrap')) {
      suggestBox.classList.remove('open');
    }
  });

  // Validate hidden field on form submit
  document.getElementById('assign-form').addEventListener('submit', function(e) {
    if (!hiddenInput.value) {
      e.preventDefault();
      searchInput.style.display = '';
      searchInput.focus();
      searchInput.style.borderColor = 'var(--red)';
      setTimeout(() => searchInput.style.borderColor = '', 2000);
    }
  });
})();

function updateExpiry() {
  const dateVal = document.getElementById('date_assigned').value;
  const months  = parseInt(document.getElementById('rental_duration').value);
  if (!dateVal) return;
  const d = new Date(dateVal);
  d.setMonth(d.getMonth() + months);
  const expiry = d.toISOString().split('T')[0];
  document.getElementById('expiry_preview').value = expiry;
}
updateExpiry();
</script>

</div></body></html>