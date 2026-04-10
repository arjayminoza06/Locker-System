<?php
session_start();
require 'db.php';

$current_page  = 'lockers';
$page_title    = 'Lockers';
$page_subtitle = 'Manage locker inventory';

// ── HANDLE ACTIONS ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $num      = strtoupper(trim($_POST['locker_number'] ?? ''));
        $building = trim($_POST['building'] ?? '');
        $floor    = (int)($_POST['floor'] ?? 0);
        $status   = $_POST['status'] ?? 'available';

        // Validate L-XXX format (L- followed by exactly 3 digits)
        if (!$num) {
            $_SESSION['toast_msg']  = 'Locker number is required.';
            $_SESSION['toast_type'] = 'error';
        } elseif (!preg_match('/^L-\d{3}$/', $num)) {
            $_SESSION['toast_msg']  = 'Invalid locker ID format. Use L-XXX (e.g. L-001, L-042, L-123).';
            $_SESSION['toast_type'] = 'error';
        } elseif (!$building) {
            $_SESSION['toast_msg']  = 'Building is required.';
            $_SESSION['toast_type'] = 'error';
        } elseif ($floor < 1) {
            $_SESSION['toast_msg']  = 'Floor must be 1 or higher.';
            $_SESSION['toast_type'] = 'error';
        } else {
            $location = $building . ' - Floor ' . $floor;
            // UNIQUENESS RULE: duplicate only if BOTH locker_number AND location are identical
            if (db_col($pdo,
                "SELECT COUNT(*) FROM lockers WHERE locker_number=? AND location=?",
                [$num, $location]) > 0) {
                $_SESSION['toast_msg']  = $num . ' already exists in ' . $location . '. Same locker number is allowed in a different building or floor.';
                $_SESSION['toast_type'] = 'error';
            } else {
                db_run($pdo,
                    "INSERT INTO lockers (locker_number, location, status) VALUES(?, ?, ?)",
                    [$num, $location, $status]);
                $_SESSION['toast_msg']  = 'Locker ' . $num . ' added successfully! Location: ' . $location . '.';
                $_SESSION['toast_type'] = 'success';
            }
        }
    }

    if ($act === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $num      = strtoupper(trim($_POST['locker_number'] ?? ''));
        $building = trim($_POST['building'] ?? '');
        $floor    = (int)($_POST['floor'] ?? 0);
        $status   = $_POST['status'] ?? 'available';

        if (!$num) {
            $_SESSION['toast_msg']  = 'Locker number is required.';
            $_SESSION['toast_type'] = 'error';
        } elseif (!preg_match('/^L-\d{3}$/', $num)) {
            $_SESSION['toast_msg']  = 'Invalid locker ID format. Use L-XXX (e.g. L-001, L-042, L-123).';
            $_SESSION['toast_type'] = 'error';
        } elseif (!$building) {
            $_SESSION['toast_msg']  = 'Building is required.';
            $_SESSION['toast_type'] = 'error';
        } elseif ($floor < 1) {
            $_SESSION['toast_msg']  = 'Floor must be 1 or higher.';
            $_SESSION['toast_type'] = 'error';
        } else {
            $location = $building . ' - Floor ' . $floor;
            // UNIQUENESS RULE: block only if locker_number + location match another row (not this one)
            if (db_col($pdo,
                "SELECT COUNT(*) FROM lockers WHERE locker_number=? AND location=? AND id<>?",
                [$num, $location, $id]) > 0) {
                $_SESSION['toast_msg']  = $num . ' already exists in ' . $location . '. Same locker number is allowed in a different building or floor.';
                $_SESSION['toast_type'] = 'error';
            } else {
                db_run($pdo,
                    "UPDATE lockers SET locker_number=?, location=?, status=? WHERE id=?",
                    [$num, $location, $status, $id]);
                $_SESSION['toast_msg']  = 'Locker updated! Location: ' . $location . '.';
                $_SESSION['toast_type'] = 'success';
            }
        }
    }

    if ($act === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $row = db_one($pdo, "SELECT status FROM lockers WHERE id=?", [$id]);
        if ($row && $row['status'] === 'occupied') {
            $_SESSION['toast_msg']  = 'Cannot delete an occupied locker.';
            $_SESSION['toast_type'] = 'error';
        } else {
            db_run($pdo, "DELETE FROM lockers WHERE id=?", [$id]);
            $_SESSION['toast_msg']  = 'Locker deleted.';
            $_SESSION['toast_type'] = 'success';
        }
    }

    header("Location: lockers.php"); exit;
}

// ── LOAD DATA ───────────────────────────────────────
$search   = trim($_GET['q'] ?? '');
$edit_id  = (int)($_GET['edit'] ?? 0);
$edit_row = $edit_id ? db_one($pdo, "SELECT * FROM lockers WHERE id=?", [$edit_id]) : null;

// Parse building & floor from existing location string (for edit mode)
$edit_building = '';
$edit_floor    = 1;
if ($edit_row && $edit_row['location']) {
    // Expected format: "Building X - Floor N"
    if (preg_match('/^(.+?)\s*-\s*Floor\s*(\d+)$/i', $edit_row['location'], $m)) {
        $edit_building = trim($m[1]);
        $edit_floor    = (int)$m[2];
    } else {
        $edit_building = $edit_row['location']; // fallback: whole string as building
    }
}

$lockers   = $search
    ? db_all($pdo,
        "SELECT * FROM lockers
         WHERE locker_number LIKE ? OR location LIKE ? OR status LIKE ?
         ORDER BY locker_number",
        ["%$search%", "%$search%", "%$search%"])
    : db_all($pdo, "SELECT * FROM lockers ORDER BY locker_number");

// Fetch buildings from the buildings table (dynamic)
$buildings = db_all($pdo, "SELECT * FROM buildings ORDER BY name");

include 'header.php';
?>

<style>
/* ── FORMAT HINT & VALIDATION ── */
.locker-id-hint {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.locker-id-hint.valid   { color: var(--success); }
.locker-id-hint.invalid { color: var(--danger);  }

.input-validated {
    position: relative;
}
.input-validated input {
    padding-right: 38px;
}
.input-validated .val-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    pointer-events: none;
    display: none;
}

/* Format badge shown inside input wrapper */
.format-badge {
    display: inline-block;
    background: var(--navy-dark);
    color: var(--gold);
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    letter-spacing: 1px;
    margin-left: 6px;
    vertical-align: middle;
}

/* Location preview pill */
.location-preview {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #e8f0fe;
    color: var(--navy);
    font-size: 12px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 20px;
    margin-top: 6px;
    min-height: 28px;
    letter-spacing: 0.2px;
    transition: opacity 0.2s;
}
.location-preview.empty {
    background: var(--gray-light);
    color: var(--text-muted);
    font-weight: 400;
}

/* No buildings warning */
.no-buildings-notice {
    background: var(--warning-bg);
    border: 1px solid #e6c97a;
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    font-size: 13px;
    color: var(--warning);
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}
</style>

<!-- ADD / EDIT FORM -->
<div class="form-box">
  <div class="form-box-title">
    <?= $edit_row ? '✏️ Edit Locker' : '➕ Add New Locker' ?>
  </div>

  <?php if (empty($buildings)): ?>
  <div class="no-buildings-notice">
    ⚠️ No buildings found. Please <a href="buildings.php" style="color:var(--warning);font-weight:700;text-decoration:underline;">add a building</a> first before adding lockers.
  </div>
  <?php endif; ?>

  <form method="POST" action="lockers.php" id="locker-form" onsubmit="return validateLockerForm()">
    <input type="hidden" name="action" value="<?= $edit_row ? 'edit' : 'add' ?>">
    <?php if ($edit_row): ?>
      <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
    <?php endif; ?>

    <div class="form-grid form-grid-3">

      <!-- ① LOCKER NUMBER with L-XXX validation -->
      <div class="form-group">
        <label>
          Locker Number *
          <span class="format-badge">L-XXX</span>
        </label>
        <div class="input-validated">
          <input type="text"
                 name="locker_number"
                 id="locker_number"
                 value="<?= e($edit_row['locker_number'] ?? '') ?>"
                 placeholder="e.g. L-001"
                 maxlength="5"
                 autocomplete="off"
                 required
                 oninput="validateLockerId(this); checkDuplicateLive()">
          <span class="val-icon" id="val_icon"></span>
        </div>
        <div class="locker-id-hint" id="locker_hint">
          Format: L- followed by 3 digits &nbsp;(L-001 to L-999)
        </div>
      </div>

      <!-- ② BUILDING — dynamic dropdown from buildings table -->
      <div class="form-group">
        <label>Building *</label>
        <select name="building" id="building_select" required onchange="updateLocationPreview(); checkDuplicateLive()">
          <option value="">-- Select Building --</option>
          <?php foreach ($buildings as $b): ?>
          <option value="<?= e($b['name']) ?>"
            <?= ($edit_building === $b['name']) ? 'selected' : '' ?>>
            <?= e($b['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($buildings)): ?>
          <div class="locker-id-hint">
            ⚠️ No buildings available — <a href="buildings.php">add one first</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- ③ FLOOR -->
      <div class="form-group">
        <label>Floor *</label>
        <select name="floor" id="floor_select" required onchange="updateLocationPreview(); checkDuplicateLive()">
          <?php for ($f = 1; $f <= 10; $f++): ?>
          <option value="<?= $f ?>" <?= ($edit_floor === $f) ? 'selected' : '' ?>>
            Floor <?= $f ?>
          </option>
          <?php endfor; ?>
        </select>
      </div>

      <!-- STATUS -->
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <?php
          $statuses = ['available' => 'Available', 'maintenance' => 'Maintenance'];
          if ($edit_row && $edit_row['status'] === 'occupied') {
              $statuses['occupied'] = 'Occupied';
          }
          foreach ($statuses as $val => $label):
              $sel = ($edit_row && $edit_row['status'] === $val) ? 'selected' : '';
          ?>
          <option value="<?= $val ?>" <?= $sel ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- LOCATION PREVIEW (read-only, auto-built) -->
      <div class="form-group" style="grid-column: span 2;">
        <label>Location Preview <small style="color:var(--text-muted);font-weight:400;">(auto-combined)</small></label>
        <div id="location_preview" class="location-preview empty">
          📍 Will show combined location here
        </div>
        <!-- hidden field carries the combined value — not needed server-side (server builds it),
             but shows admin exactly what will be saved -->
      </div>

    </div><!-- /form-grid -->

    <div class="form-actions">
      <button type="submit" class="btn btn-primary" <?= empty($buildings) ? 'disabled title="Add a building first"' : '' ?>>
        <?= $edit_row ? '💾 Save Changes' : '➕ Add Locker' ?>
      </button>
      <?php if ($edit_row): ?>
        <a href="lockers.php" class="btn btn-ghost">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- LOCKERS TABLE -->
<div class="table-box">
  <div class="table-header">
    <h3>All Lockers <span class="count-badge"><?= count($lockers) ?></span></h3>
    <div class="search-bar">
      <form method="GET" action="lockers.php" style="display:contents">
        <div class="search-input-wrap">
          <input type="text" name="q" placeholder="Search lockers…"
                 value="<?= e($search) ?>" onchange="this.form.submit()">
        </div>
      </form>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Locker Number</th>
        <th>Building</th>
        <th>Floor</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($lockers)): ?>
        <tr class="empty-row"><td colspan="6">No lockers found</td></tr>
      <?php else: ?>
        <?php foreach ($lockers as $i => $l):
          // Parse location into building + floor for display
          $disp_building = $l['location'] ?? '—';
          $disp_floor    = '—';
          if ($l['location'] && preg_match('/^(.+?)\s*-\s*Floor\s*(\d+)$/i', $l['location'], $pm)) {
              $disp_building = trim($pm[1]);
              $disp_floor    = 'Floor ' . $pm[2];
          }
        ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td>
            <strong style="font-family:monospace;letter-spacing:0.5px;">
              <?= e($l['locker_number']) ?>
            </strong>
          </td>
          <td><?= e($disp_building) ?></td>
          <td>
            <?php if ($disp_floor !== '—'): ?>
              <span class="badge" style="background:#e8f0fe;color:#1a5276;">
                🏬 <?= e($disp_floor) ?>
              </span>
            <?php else: ?>
              <span style="color:var(--gray);">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= e($l['status']) ?>">
              <?= ucfirst(e($l['status'])) ?>
            </span>
          </td>
          <td>
            <a href="lockers.php?edit=<?= $l['id'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
            <?php if ($l['status'] !== 'occupied'): ?>
            <form method="POST" action="lockers.php" style="display:inline"
                  onsubmit="return confirm('Delete this locker?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" style="margin-left:6px">🗑️</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
// ── EXISTING LOCKERS for client-side duplicate pre-check ──
// Injected by PHP: array of {num, location} for all current rows
const EXISTING_LOCKERS = <?php
    $all_for_js = db_all($pdo,
        "SELECT id, locker_number, location FROM lockers");
    echo json_encode(array_map(fn($r) => [
        'id'       => (int)$r['id'],
        'num'      => $r['locker_number'],
        'location' => $r['location'],
    ], $all_for_js));
?>;

// The ID being edited right now (0 = add mode)
const EDIT_ID = <?= $edit_id ?>;

// ── L-XXX FORMAT VALIDATION ──────────────────────────
const LOCKER_REGEX = /^L-\d{3}$/;

function validateLockerId(input) {
    const raw   = input.value.toUpperCase();
    input.value = raw; // auto-uppercase

    const hint = document.getElementById('locker_hint');
    const icon = document.getElementById('val_icon');

    if (!raw) {
        hint.textContent  = 'Format: L- followed by 3 digits  (L-001 to L-999)';
        hint.className    = 'locker-id-hint';
        icon.style.display = 'none';
        input.style.borderColor = '';
        return;
    }

    // Live partial hint while typing
    if (raw.length < 5) {
        hint.textContent  = 'Keep typing… expected format: L-XXX';
        hint.className    = 'locker-id-hint';
        icon.style.display = 'none';
        input.style.borderColor = '';
        return;
    }

    if (LOCKER_REGEX.test(raw)) {
        hint.textContent   = '✓ Valid locker ID format';
        hint.className     = 'locker-id-hint valid';
        icon.textContent   = '✅';
        icon.style.display = 'block';
        input.style.borderColor = 'var(--success)';
    } else {
        hint.textContent   = '✗ Invalid format — must be L-XXX (e.g. L-001)';
        hint.className     = 'locker-id-hint invalid';
        icon.textContent   = '❌';
        icon.style.display = 'block';
        input.style.borderColor = 'var(--danger)';
    }
}

// ── LOCATION PREVIEW ────────────────────────────────
function updateLocationPreview() {
    const building = document.getElementById('building_select').value;
    const floor    = document.getElementById('floor_select').value;
    const preview  = document.getElementById('location_preview');

    if (building && floor) {
        preview.textContent = '📍 ' + building + ' - Floor ' + floor;
        preview.classList.remove('empty');
    } else {
        preview.textContent = '📍 Will show combined location here';
        preview.classList.add('empty');
    }
}

// ── FORM SUBMIT VALIDATION ───────────────────────────
function validateLockerForm() {
    const num      = document.getElementById('locker_number').value.trim().toUpperCase();
    const building = document.getElementById('building_select').value;
    const floor    = document.getElementById('floor_select').value;

    if (!LOCKER_REGEX.test(num)) {
        alert('❌ Invalid Locker ID format.\n\nRequired: L-XXX  (e.g. L-001, L-042, L-123)\nProvided: "' + num + '"');
        document.getElementById('locker_number').focus();
        return false;
    }
    if (!building) {
        alert('⚠️ Please select a building.');
        document.getElementById('building_select').focus();
        return false;
    }
    if (!floor || parseInt(floor) < 1) {
        alert('⚠️ Please select a floor.');
        document.getElementById('floor_select').focus();
        return false;
    }

    // ── UNIQUENESS PRE-CHECK ──────────────────────────
    // Duplicate = same locker_number AND same location, excluding the row being edited
    const location = building + ' - Floor ' + floor;
    const duplicate = EXISTING_LOCKERS.find(r =>
        r.num === num &&
        r.location === location &&
        r.id !== EDIT_ID
    );
    if (duplicate) {
        alert(
            '❌ Duplicate locker detected!\n\n' +
            'Locker "' + num + '" already exists at "' + location + '".\n\n' +
            '✅ Allowed: same number in a different building  (e.g. Building B - Floor 1)\n' +
            '✅ Allowed: same number on a different floor  (e.g. Building A - Floor 2)\n' +
            '❌ Not allowed: identical number + building + floor combination.'
        );
        return false;
    }

    return true;
}

// ── LIVE DUPLICATE INDICATOR on input/select change ──
function checkDuplicateLive() {
    const num      = (document.getElementById('locker_number').value || '').trim().toUpperCase();
    const building = document.getElementById('building_select').value;
    const floor    = document.getElementById('floor_select').value;
    const hint     = document.getElementById('locker_hint');

    if (!num || !building || !floor) return; // nothing to check yet
    if (!LOCKER_REGEX.test(num)) return;     // format error shown separately

    const location  = building + ' - Floor ' + floor;
    const duplicate = EXISTING_LOCKERS.find(r =>
        r.num === num &&
        r.location === location &&
        r.id !== EDIT_ID
    );

    if (duplicate) {
        hint.textContent = '⚠️ ' + num + ' already exists in ' + location + ' — change building or floor.';
        hint.className   = 'locker-id-hint invalid';
        document.getElementById('locker_number').style.borderColor = 'var(--danger)';
    } else if (LOCKER_REGEX.test(num)) {
        // Show "available at this location" confirmation
        const anywhereElse = EXISTING_LOCKERS.filter(r =>
            r.num === num && r.id !== EDIT_ID
        );
        if (anywhereElse.length > 0) {
            hint.textContent = '✓ ' + num + ' exists elsewhere but is free at ' + location + '.';
        } else {
            hint.textContent = '✓ Valid locker ID format';
        }
        hint.className = 'locker-id-hint valid';
        document.getElementById('locker_number').style.borderColor = 'var(--success)';
    }
}

// ── INIT on page load ────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const numInput = document.getElementById('locker_number');
    if (numInput.value) validateLockerId(numInput);
    updateLocationPreview();
    checkDuplicateLive();
});
</script>

</div><!-- /main -->
</body>
</html>