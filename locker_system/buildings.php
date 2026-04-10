<?php
session_start();
require 'db.php';

$current_page  = 'buildings';
$page_title    = 'Buildings';
$page_subtitle = 'Manage and monitor lockers per building';

// ── HANDLE ACTIONS ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!$name) {
            $_SESSION['toast_msg']  = 'Building name is required.';
            $_SESSION['toast_type'] = 'error';
        } elseif (db_col($pdo, "SELECT COUNT(*) FROM buildings WHERE name=?", [$name]) > 0) {
            $_SESSION['toast_msg']  = 'A building with that name already exists.';
            $_SESSION['toast_type'] = 'error';
        } else {
            db_run($pdo, "INSERT INTO buildings (name, description) VALUES (?, ?)", [$name, $desc]);
            $_SESSION['toast_msg']  = $name . ' added successfully!';
            $_SESSION['toast_type'] = 'success';
        }
    }

    if ($act === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $bld = db_one($pdo, "SELECT * FROM buildings WHERE id=?", [$id]);
        if ($bld) {
            // Check if any lockers reference this building
            $lockerCount = db_col($pdo,
                "SELECT COUNT(*) FROM lockers WHERE location LIKE ?",
                [$bld['name'] . '%']);
            if ($lockerCount > 0) {
                $_SESSION['toast_msg']  = 'Cannot delete: ' . $lockerCount . ' locker(s) are assigned to this building.';
                $_SESSION['toast_type'] = 'error';
            } else {
                db_run($pdo, "DELETE FROM buildings WHERE id=?", [$id]);
                $_SESSION['toast_msg']  = 'Building deleted.';
                $_SESSION['toast_type'] = 'success';
            }
        }
    }

    header("Location: buildings.php"); exit;
}

// ── LOAD BUILDINGS WITH LOCKER STATS ────────────────
$buildings = db_all($pdo, "SELECT * FROM buildings ORDER BY name");

// For each building, get locker stats and locker list
$building_data = [];
foreach ($buildings as $b) {
    $pattern = $b['name'] . '%'; // matches "Building A - Floor 1", "Building A - Floor 2", etc.
    $lockers = db_all($pdo,
        "SELECT l.*, la.student_id as assigned_student_id,
                s.full_name as assigned_to, la.expiry_date
         FROM lockers l
         LEFT JOIN locker_assignments la
           ON l.id = la.locker_id AND la.date_returned IS NULL
         LEFT JOIN students s ON la.student_id = s.id
         WHERE l.location LIKE ?
         ORDER BY l.locker_number",
        [$pattern]);

    $total       = count($lockers);
    $occupied    = 0; $available = 0; $maintenance = 0;
    foreach ($lockers as $lk) {
        if ($lk['status'] === 'occupied')     $occupied++;
        elseif ($lk['status'] === 'available') $available++;
        else                                   $maintenance++;
    }

    $building_data[] = [
        'info'        => $b,
        'lockers'     => $lockers,
        'total'       => $total,
        'occupied'    => $occupied,
        'available'   => $available,
        'maintenance' => $maintenance,
    ];
}

// Filter: which building tab is active
$active_tab = (int)($_GET['tab'] ?? 0);

include 'header.php';
?>

<style>
/* ── BUILDING TABS ── */
.building-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 24px;
}
.building-tab {
  padding: 9px 20px;
  border-radius: 30px;
  border: 2px solid var(--gray-light);
  background: white;
  color: var(--text-muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 8px;
}
.building-tab:hover  { border-color: var(--navy); color: var(--navy); }
.building-tab.active { background: var(--navy); color: white; border-color: var(--navy); }
.building-tab .tab-count {
  background: rgba(255,255,255,0.25);
  padding: 1px 7px;
  border-radius: 10px;
  font-size: 11px;
}
.building-tab:not(.active) .tab-count {
  background: var(--gray-light);
  color: var(--text-muted);
}

/* ── BUILDING STATS ROW ── */
.building-stats {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.bstat {
  flex: 1;
  min-width: 130px;
  background: white;
  border-radius: var(--radius);
  padding: 16px 20px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--gray-light);
  display: flex;
  align-items: center;
  gap: 14px;
}
.bstat-icon {
  width: 44px; height: 44px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.bstat-info .num  { font-size: 26px; font-weight: 700; color: var(--navy-dark); line-height: 1; }
.bstat-info .lbl  { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; font-weight: 600; }

/* ── LOCKER GRID ── */
.locker-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 14px;
  margin-top: 8px;
}

/* ── LOCKER BOX ── */
.locker-box {
  border-radius: 10px;
  padding: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: default;
  transition: transform 0.15s, box-shadow 0.15s;
  position: relative;
  overflow: hidden;
  border: 2px solid transparent;
  box-shadow: 0 2px 8px rgba(0,0,0,0.10);
}
.locker-box:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }

/* Locker door header stripe */
.locker-door-top {
  width: 100%;
  padding: 6px 0 5px;
  text-align: center;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

/* Locker body */
.locker-door-body {
  width: 100%;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 10px 8px 8px;
  gap: 6px;
  position: relative;
}

/* Locker number */
.locker-num {
  font-size: 15px;
  font-weight: 800;
  letter-spacing: 0.5px;
}

/* Fake handle */
.locker-handle {
  width: 18px; height: 6px;
  border-radius: 3px;
  opacity: 0.35;
  margin-top: 2px;
}

/* Fake vent lines */
.locker-vents {
  display: flex;
  flex-direction: column;
  gap: 3px;
  width: 60%;
  margin-top: 4px;
}
.locker-vent {
  height: 2px;
  border-radius: 1px;
  opacity: 0.2;
}

/* Status label */
.locker-status-label {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 10px;
  margin-bottom: 6px;
}

/* STATUS THEMES */
.locker-available {
  background: linear-gradient(160deg, #e8faf0 0%, #c8f0d8 100%);
  border-color: #5cb87a;
}
.locker-available .locker-door-top   { background: #2ecc71; color: white; }
.locker-available .locker-num        { color: #1a7a4a; }
.locker-available .locker-handle     { background: #1a7a4a; }
.locker-available .locker-vent       { background: #1a7a4a; }
.locker-available .locker-status-label { background: #d4f5e4; color: #1a7a4a; }

.locker-occupied {
  background: linear-gradient(160deg, #fde8e8 0%, #f5c0c0 100%);
  border-color: #e74c3c;
}
.locker-occupied .locker-door-top    { background: #e74c3c; color: white; }
.locker-occupied .locker-num         { color: #922b21; }
.locker-occupied .locker-handle      { background: #922b21; }
.locker-occupied .locker-vent        { background: #922b21; }
.locker-occupied .locker-status-label { background: #fde8e8; color: #c0392b; }

.locker-maintenance {
  background: linear-gradient(160deg, #fef9ec 0%, #fdeec8 100%);
  border-color: #f0b429;
}
.locker-maintenance .locker-door-top  { background: #f0b429; color: white; }
.locker-maintenance .locker-num       { color: #7d5a00; }
.locker-maintenance .locker-handle    { background: #7d5a00; }
.locker-maintenance .locker-vent      { background: #7d5a00; }
.locker-maintenance .locker-status-label { background: #fef3cc; color: #7d5a00; }

/* Tooltip on hover */
.locker-box[data-tip]:hover::after {
  content: attr(data-tip);
  position: absolute;
  bottom: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%);
  background: var(--navy-dark);
  color: white;
  font-size: 11px;
  padding: 5px 10px;
  border-radius: 6px;
  white-space: nowrap;
  z-index: 99;
  pointer-events: none;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.locker-box[data-tip]:hover::before {
  content: '';
  position: absolute;
  bottom: calc(100% + 2px);
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: var(--navy-dark);
  z-index: 99;
}

/* Floor section */
.floor-section { margin-bottom: 28px; }
.floor-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.floor-label::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--gray-light);
}

/* Empty building */
.empty-building {
  text-align: center;
  padding: 50px 20px;
  color: var(--gray);
}
.empty-building .icon { font-size: 48px; margin-bottom: 12px; }
.empty-building p { font-size: 14px; }

/* Legend */
.legend {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
}
.legend-dot {
  width: 12px; height: 12px;
  border-radius: 3px;
}
</style>

<!-- ADD BUILDING FORM -->
<div class="form-box">
  <div class="form-box-title">🏢 Add New Building</div>
  <form method="POST" action="buildings.php">
    <input type="hidden" name="action" value="add">
    <div class="form-grid form-grid-2">
      <div class="form-group">
        <label>Building Name * <small style="color:var(--text-muted);font-weight:400;">(e.g. Building A)</small></label>
        <input type="text" name="name" placeholder="Building A" required>
      </div>
      <div class="form-group">
        <label>Description <small style="color:var(--text-muted);font-weight:400;">(optional)</small></label>
        <input type="text" name="description" placeholder="e.g. Main academic building">
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">➕ Add Building</button>
    </div>
  </form>
</div>

<?php if (empty($buildings)): ?>
  <div class="table-box">
    <div class="empty-building">
      <div class="icon">🏢</div>
      <p>No buildings added yet. Add your first building above.</p>
    </div>
  </div>
<?php else: ?>

<!-- BUILDING TABS -->
<div class="building-tabs">
  <a href="buildings.php?tab=0" class="building-tab <?= $active_tab === 0 ? 'active' : '' ?>">
    🏢 All Buildings
    <span class="tab-count"><?= count($buildings) ?></span>
  </a>
  <?php foreach ($building_data as $idx => $bd): ?>
  <a href="buildings.php?tab=<?= $idx + 1 ?>" class="building-tab <?= $active_tab === ($idx + 1) ? 'active' : '' ?>">
    <?= e($bd['info']['name']) ?>
    <span class="tab-count"><?= $bd['total'] ?></span>
  </a>
  <?php endforeach; ?>
</div>

<?php
// Which buildings to show
$show = ($active_tab === 0) ? $building_data : [$building_data[$active_tab - 1]];
?>

<?php foreach ($show as $bd): ?>
<div class="table-box" style="overflow:visible;margin-bottom:28px;">
  <!-- Building Header -->
  <div style="padding:20px 24px;border-bottom:1px solid var(--gray-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;color:var(--navy-dark);margin-bottom:3px;">
        🏢 <?= e($bd['info']['name']) ?>
      </h2>
      <?php if ($bd['info']['description']): ?>
        <p style="font-size:13px;color:var(--text-muted);"><?= e($bd['info']['description']) ?></p>
      <?php endif; ?>
    </div>
    <form method="POST" action="buildings.php" style="display:inline"
          onsubmit="return confirm('Delete <?= e($bd['info']['name']) ?>? This only works if no lockers are assigned here.')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= $bd['info']['id'] ?>">
      <button type="submit" class="btn btn-danger btn-sm">🗑️ Delete Building</button>
    </form>
  </div>

  <!-- Stats Row -->
  <div style="padding:20px 24px;border-bottom:1px solid var(--gray-light);">
    <div class="building-stats">
      <div class="bstat">
        <div class="bstat-icon" style="background:#e8f0fe;">🗄️</div>
        <div class="bstat-info">
          <div class="num"><?= $bd['total'] ?></div>
          <div class="lbl">Total Lockers</div>
        </div>
      </div>
      <div class="bstat">
        <div class="bstat-icon" style="background:#fde8e8;">🔒</div>
        <div class="bstat-info">
          <div class="num" style="color:#c0392b;"><?= $bd['occupied'] ?></div>
          <div class="lbl">Assigned / Rented</div>
        </div>
      </div>
      <div class="bstat">
        <div class="bstat-icon" style="background:#d4f5e4;">✅</div>
        <div class="bstat-info">
          <div class="num" style="color:#1a7a4a;"><?= $bd['available'] ?></div>
          <div class="lbl">Available</div>
        </div>
      </div>
      <div class="bstat">
        <div class="bstat-icon" style="background:#fef3cc;">🔧</div>
        <div class="bstat-info">
          <div class="num" style="color:#b07d10;"><?= $bd['maintenance'] ?></div>
          <div class="lbl">Maintenance</div>
        </div>
      </div>
      <?php if ($bd['total'] > 0): ?>
      <div class="bstat" style="flex:2;min-width:200px;">
        <div style="width:100%;">
          <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
            Occupancy Rate
          </div>
          <div style="background:var(--gray-light);border-radius:10px;height:10px;overflow:hidden;margin-bottom:6px;">
            <?php $pct = $bd['total'] > 0 ? round($bd['occupied'] / $bd['total'] * 100) : 0; ?>
            <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct > 80 ? '#e74c3c' : ($pct > 50 ? '#f0b429' : '#2ecc71') ?>;border-radius:10px;transition:width 0.4s;"></div>
          </div>
          <div style="font-size:13px;font-weight:700;color:var(--navy-dark);"><?= $pct ?>% occupied</div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Legend -->
    <div class="legend">
      <div class="legend-item"><div class="legend-dot" style="background:#2ecc71;"></div> Available</div>
      <div class="legend-item"><div class="legend-dot" style="background:#e74c3c;"></div> Assigned / Rented</div>
      <div class="legend-item"><div class="legend-dot" style="background:#f0b429;"></div> Under Maintenance</div>
    </div>
  </div>

  <!-- Locker Visual Grid -->
  <div style="padding:24px;">
    <?php if (empty($bd['lockers'])): ?>
      <div class="empty-building">
        <div class="icon">🔐</div>
        <p>No lockers found for <?= e($bd['info']['name']) ?>.<br>
           Add lockers with location <strong>"<?= e($bd['info']['name']) ?> - Floor N"</strong> to see them here.</p>
      </div>
    <?php else: ?>
      <?php
      // Group lockers by floor
      $by_floor = [];
      foreach ($bd['lockers'] as $lk) {
          preg_match('/Floor\s+(\d+)/i', $lk['location'], $fm);
          $floor = isset($fm[1]) ? 'Floor ' . $fm[1] : 'Other';
          $by_floor[$floor][] = $lk;
      }
      ksort($by_floor);
      ?>
      <?php foreach ($by_floor as $floor => $floor_lockers): ?>
      <div class="floor-section">
        <div class="floor-label">🏬 <?= e($floor) ?> <span style="font-weight:400;font-size:11px;">(<?= count($floor_lockers) ?> lockers)</span></div>
        <div class="locker-grid">
          <?php foreach ($floor_lockers as $lk):
            $cls    = 'locker-' . $lk['status'];
            $tip    = $lk['status'] === 'occupied'
                      ? 'Rented by: ' . ($lk['assigned_to'] ?? 'Unknown') . ($lk['expiry_date'] ? ' · Expires: ' . $lk['expiry_date'] : '')
                      : ($lk['status'] === 'maintenance' ? ($lk['maint_reason'] ?? 'Under maintenance') : 'Available for rent');
            $slabel = $lk['status'] === 'occupied' ? 'Assigned' : ($lk['status'] === 'maintenance' ? 'Maintenance' : 'Available');
            $sicon  = $lk['status'] === 'occupied' ? '🔒' : ($lk['status'] === 'maintenance' ? '🔧' : '🔓');
          ?>
          <div class="locker-box <?= $cls ?>" data-tip="<?= e($tip) ?>">
            <div class="locker-door-top"><?= $sicon ?> <?= $slabel ?></div>
            <div class="locker-door-body">
              <div class="locker-num"><?= e($lk['locker_number']) ?></div>
              <div class="locker-handle"></div>
              <div class="locker-vents">
                <div class="locker-vent"></div>
                <div class="locker-vent"></div>
                <div class="locker-vent"></div>
              </div>
              <div class="locker-status-label"><?= $slabel ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- BUILDINGS SUMMARY TABLE (shown in All view) -->
<?php if ($active_tab === 0 && !empty($building_data)): ?>
<div class="table-box">
  <div class="table-header">
    <h3>📊 Buildings Summary</h3>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Building</th>
        <th>Description</th>
        <th>Total Lockers</th>
        <th>Assigned</th>
        <th>Available</th>
        <th>Maintenance</th>
        <th>Occupancy</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($building_data as $i => $bd): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><strong><?= e($bd['info']['name']) ?></strong></td>
        <td style="color:var(--text-muted);font-size:13px;"><?= e($bd['info']['description'] ?: '—') ?></td>
        <td><span class="badge" style="background:#e8f0fe;color:#1a5276;"><?= $bd['total'] ?></span></td>
        <td><span class="badge badge-occupied"><?= $bd['occupied'] ?></span></td>
        <td><span class="badge badge-available"><?= $bd['available'] ?></span></td>
        <td><span class="badge badge-maintenance"><?= $bd['maintenance'] ?></span></td>
        <td>
          <?php $pct = $bd['total'] > 0 ? round($bd['occupied'] / $bd['total'] * 100) : 0; ?>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="background:var(--gray-light);border-radius:6px;height:7px;width:80px;overflow:hidden;">
              <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct > 80 ? '#e74c3c' : ($pct > 50 ? '#f0b429' : '#2ecc71') ?>;border-radius:6px;"></div>
            </div>
            <span style="font-size:12px;font-weight:700;color:var(--text-muted);"><?= $pct ?>%</span>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php endif; ?>

</div></body></html>