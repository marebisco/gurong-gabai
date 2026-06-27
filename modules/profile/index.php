<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();
$tid = $_SESSION['teacher_id'];
$teacher = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM teachers WHERE id='$tid'"));
$errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name   = mysqli_real_escape_string($conn, trim($_POST['full_name']   ?? ''));
        $school = mysqli_real_escape_string($conn, trim($_POST['school_name'] ?? ''));
        if (!$name || !$school) { $errors[] = 'Name and school are required.'; }
        else {
            mysqli_query($conn, "UPDATE teachers SET full_name='$name', school_name='$school' WHERE id='$tid'");
            $_SESSION['teacher_name'] = $name;
            $success = 'Profile updated successfully!';
            $teacher = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM teachers WHERE id='$tid'"));
        }
    }

    if ($action === 'change_password') {
        $curr = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (!password_verify($curr, $teacher['password'])) { $errors[] = 'Current password is incorrect.'; }
        elseif (strlen($new) < 8)  { $errors[] = 'New password must be at least 8 characters.'; }
        elseif ($new !== $conf)    { $errors[] = 'Passwords do not match.'; }
        else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            mysqli_query($conn, "UPDATE teachers SET password='$hash' WHERE id='$tid'");
            $success = 'Password changed successfully!';
        }
    }

    if ($action === 'upload_photo' && isset($_FILES['photo'])) {
        $file  = $_FILES['photo'];
        $allow = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($file['type'], $allow)) { $errors[] = 'Only JPG, PNG, or GIF allowed.'; }
        elseif ($file['size'] > 2 * 1024 * 1024) { $errors[] = 'Max file size is 2MB.'; }
        else {
            $dir = __DIR__ . '/../../assets/uploads/photos/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            // Delete old photo
            if (!empty($teacher['profile_photo']) && file_exists($dir . $teacher['profile_photo'])) {
                unlink($dir . $teacher['profile_photo']);
            }
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'teacher_' . $tid . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                mysqli_query($conn, "UPDATE teachers SET profile_photo='$filename' WHERE id='$tid'");
                $_SESSION['teacher_photo'] = $filename;
                $success = 'Profile photo updated!';
                $teacher = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM teachers WHERE id='$tid'"));
            } else { $errors[] = 'Upload failed. Check folder permissions.'; }
        }
    }

    if ($action === 'remove_photo') {
        $dir = __DIR__ . '/../../assets/uploads/photos/';
        if (!empty($teacher['profile_photo']) && file_exists($dir . $teacher['profile_photo'])) {
            unlink($dir . $teacher['profile_photo']);
        }
        mysqli_query($conn, "UPDATE teachers SET profile_photo=NULL WHERE id='$tid'");
        $_SESSION['teacher_photo'] = null;
        $success = 'Profile photo removed.';
        $teacher = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM teachers WHERE id='$tid'"));
    }
}

$photoUrl = !empty($teacher['profile_photo'])
    ? '/gurong-gabai/assets/uploads/photos/' . $teacher['profile_photo']
    : null;
$initials = strtoupper(substr($teacher['full_name'] ?? 'T', 0, 2));

$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(is_saved=1 AND deleted_at IS NULL) saved, COALESCE(SUM(export_count),0) exported
     FROM lesson_plans WHERE teacher_id='$tid'"));

$page_title = "My Profile — Gurong GabAI";
$active_nav = 'profile';
include '../../includes/header.php';
?>
<div class="app-shell">
<?php include '../../includes/sidenav.php'; ?>
<div class="main">

  <?php if ($success): ?><div class="alert alert-success" style="max-width:720px;margin-bottom:14px;">✅ <?=htmlspecialchars($success)?></div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-error" style="max-width:720px;margin-bottom:14px;">⚠️ <?=implode('<br>',array_map('htmlspecialchars',$errors))?></div><?php endif; ?>

  <!-- Profile Banner -->
  <div style="background:linear-gradient(135deg,#060c18,#0f1e3d,#1a3468);border-radius:16px 16px 0 0;padding:28px 24px 60px;position:relative;max-width:720px;">
    <div style="font-size:15px;font-weight:700;color:#fff;margin-bottom:3px;"><?=htmlspecialchars($teacher['full_name'])?></div>
    <div style="font-size:12px;color:rgba(255,255,255,.45);"><?=htmlspecialchars($teacher['school_name'])?></div>
  </div>

  <div style="background:var(--color-background-primary);border:1px solid var(--color-border-secondary);
              border-top:none;border-radius:0 0 16px 16px;padding:0 24px 24px;max-width:720px;margin-bottom:16px;">

    <!-- Avatar + Photo Controls -->
    <div style="display:flex;align-items:flex-end;gap:16px;margin-top:-36px;margin-bottom:20px;">
      <div style="position:relative;flex-shrink:0;">
        <?php if ($photoUrl): ?>
          <img src="<?=$photoUrl?>" alt="Profile"
               style="width:72px;height:72px;border-radius:50%;object-fit:cover;
                      border:3px solid var(--color-background-primary);
                      box-shadow:0 4px 14px rgba(37,99,235,.3);">
        <?php else: ?>
          <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#8b5cf6);
                      display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;
                      color:#fff;border:3px solid var(--color-background-primary);
                      box-shadow:0 4px 14px rgba(37,99,235,.3);">
            <?=$initials?>
          </div>
        <?php endif; ?>
      </div>
      <div style="padding-bottom:4px;">
        <span class="badge b-approved" style="margin-bottom:6px;display:inline-block;">✓ Active Teacher</span>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <!-- Upload photo -->
          <form method="POST" enctype="multipart/form-data" style="display:inline;">
            <input type="hidden" name="action" value="upload_photo">
            <label style="padding:5px 12px;background:var(--color-background-info);color:var(--color-text-info);
                          border:1px solid var(--color-border-info);border-radius:7px;font-size:12px;
                          font-weight:600;cursor:pointer;">
              📷 Change Photo
              <input type="file" name="photo" accept="image/*" style="display:none;"
                     onchange="this.form.submit()">
            </label>
          </form>
          <?php if ($photoUrl): ?>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Remove profile photo?')">
            <input type="hidden" name="action" value="remove_photo">
            <button style="padding:5px 12px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;
                           border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">✕ Remove</button>
          </form>
          <?php endif; ?>
        </div>
        <div style="font-size:10px;color:var(--color-text-secondary);margin-top:4px;">JPG, PNG · max 2MB</div>
      </div>
    </div>

    <!-- Info grid -->
    <div class="info-grid" style="margin-bottom:16px;">
      <div class="info-cell"><div class="info-label">Email</div><div class="info-val"><?=htmlspecialchars($teacher['email'])?></div></div>
      <div class="info-cell"><div class="info-label">Member Since</div><div class="info-val"><?=date('F d, Y',strtotime($teacher['created_at']))?></div></div>
      <div class="info-cell"><div class="info-label">Saved Plans</div><div class="info-val"><?=(int)$stats['saved']?> plan(s)</div></div>
      <div class="info-cell"><div class="info-label">Total Exports</div><div class="info-val"><?=(int)$stats['exported']?> export(s)</div></div>
    </div>

    <!-- Edit Profile form -->
    <form method="POST" style="margin-bottom:20px;">
      <input type="hidden" name="action" value="update_profile">
      <div style="font-size:13px;font-weight:600;color:var(--color-text-primary);margin-bottom:12px;">✏️ Edit Profile</div>
      <div class="field-row">
        <div class="field"><label>Full Name</label><input type="text" name="full_name" value="<?=htmlspecialchars($teacher['full_name'])?>" required></div>
        <div class="field"><label>School Name</label><input type="text" name="school_name" value="<?=htmlspecialchars($teacher['school_name'])?>" required></div>
      </div>
      <div class="field"><label>School Email <span style="color:var(--color-text-secondary);font-weight:400">(cannot be changed)</span></label>
        <input type="email" value="<?=htmlspecialchars($teacher['email'])?>" disabled style="opacity:.6;cursor:not-allowed;"></div>
      <button type="submit" class="btn btn-blue" style="max-width:180px;">💾 Save Changes</button>
    </form>

    <!-- Change Password form -->
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <div style="font-size:13px;font-weight:600;color:var(--color-text-primary);margin-bottom:12px;">🔒 Change Password</div>
      <div class="field-row">
        <div class="field"><label>Current Password</label><input type="password" name="current_password" required></div>
        <div class="field"><label>New Password</label><input type="password" name="new_password" placeholder="Min. 8 characters" required></div>
      </div>
      <div class="field" style="max-width:340px;"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
      <button type="submit" class="btn btn-blue" style="max-width:200px;">🔑 Change Password</button>
    </form>
  </div>
</div></div>
<?php include '../../includes/footer.php'; ?>