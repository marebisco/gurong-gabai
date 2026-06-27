<?php
$active_nav = $active_nav ?? '';
$initials   = strtoupper(substr($_SESSION['teacher_name'] ?? 'T', 0, 2));
$name       = htmlspecialchars($_SESSION['teacher_name'] ?? 'Teacher');
$photoUrl   = !empty($_SESSION['teacher_photo'] ?? null)
    ? '/gurong-gabai/assets/uploads/photos/' . $_SESSION['teacher_photo']
    : null;
?>
<nav class="sidebar" id="mainSidebar">

  <div class="sb-brand">
    <div class="sb-dot" style="font-size:13px;font-weight:800;color:#fff;letter-spacing:-1px;">G</div>
    <span class="nav-text" style="font-size:14px;font-weight:700;color:#f1f5f9;letter-spacing:-0.2px;">
      Gurong <em style="color:#60a5fa;font-style:normal;">GabAI</em>
    </span>
  </div>

  <div class="nav-grp">
    <div class="nav-lbl">Main Menu</div>
    <a href="/gurong-gabai/modules/dashboard/index.php" class="nav-a <?= $active_nav==='dashboard'?'on':'' ?>">
      <span class="ico"><i class="bi bi-house"></i></span>
      <span class="nav-text"> Dashboard</span>
    </a>
    <a href="/gurong-gabai/modules/generator/index.php" class="nav-a <?= $active_nav==='generator'?'on':'' ?>">
      <span class="ico"><i class="bi bi-stars"></i></span>
      <span class="nav-text"> AI Generator</span>
    </a>
    <a href="/gurong-gabai/modules/library/index.php" class="nav-a <?= $active_nav==='library'?'on':'' ?>">
      <span class="ico"><i class="bi bi-collection"></i></span>
      <span class="nav-text"> Resource Library</span>
    </a>
    <a href="/gurong-gabai/modules/library/trash.php" class="nav-a <?= $active_nav==='trash'?'on':'' ?>">
      <span class="ico"><i class="bi bi-trash"></i></span>
      <span class="nav-text"> Trash</span>
    </a>
    <a href="/gurong-gabai/modules/history/index.php" class="nav-a <?= $active_nav==='history'?'on':'' ?>">
      <span class="ico"><i class="bi bi-clock-history"></i></span>
      <span class="nav-text"> History</span>
    </a>
  </div>

  <div class="nav-grp">
    <div class="nav-lbl">Account</div>
    <a href="/gurong-gabai/modules/profile/index.php" class="nav-a <?= $active_nav==='profile'?'on':'' ?>">
      <span class="ico"><i class="bi bi-person-circle"></i></span>
      <span class="nav-text"> My Profile</span>
    </a>
    <a href="#" class="nav-a" onclick="confirmLogout(event)">
      <span class="ico"><i class="bi bi-box-arrow-right"></i></span>
      <span class="nav-text"> Log Out</span>
    </a>
  </div>

  <div class="sb-bottom">
    <button class="collapse-btn" onclick="toggleSidebar()" id="collapseBtn" title="Collapse sidebar">
      <span id="collapseIcon"><i class="bi bi-layout-sidebar-reverse"></i></span>
      <span class="nav-text" style="font-size:12px;margin-left:6px;">Collapse</span>
    </button>
    <button class="dark-toggle" onclick="toggleDark()">
      <div class="toggle-track"><div class="toggle-thumb"></div></div>
      <span id="theme-label" class="nav-text">Dark Mode</span>
    </button>
    <div class="user-chip">
      <div class="user-ava" style="<?= $photoUrl ? 'padding:0;overflow:hidden;' : '' ?>">
        <?php if ($photoUrl): ?>
          <img src="<?= htmlspecialchars($photoUrl) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
        <?php else: ?>
          <?= $initials ?>
        <?php endif; ?>
      </div>
      <div class="nav-text">
        <div class="user-name"><?= $name ?></div>
        <div class="user-role">Teacher · Active</div>
      </div>
    </div>
  </div>

</nav>
<script>
function toggleSidebar() {
  const sidebar   = document.getElementById('mainSidebar');
  const shell     = document.querySelector('.app-shell');
  const icon      = document.getElementById('collapseIcon');
  const collapsed = sidebar.classList.toggle('collapsed');
  if (shell) shell.classList.toggle('collapsed', collapsed);
  icon.innerHTML = collapsed
    ? '<i class="bi bi-layout-sidebar"></i>'
    : '<i class="bi bi-layout-sidebar-reverse"></i>';
  localStorage.setItem('sidebarCollapsed', collapsed);
}
(function() {
  if (localStorage.getItem('sidebarCollapsed') === 'true') {
    const sidebar = document.getElementById('mainSidebar');
    const shell   = document.querySelector('.app-shell');
    const icon    = document.getElementById('collapseIcon');
    sidebar?.classList.add('collapsed');
    shell?.classList.add('collapsed');
    if (icon) icon.innerHTML = '<i class="bi bi-layout-sidebar"></i>';
  }
})();
function confirmLogout(e) {
  e.preventDefault();
  if (confirm('Are you sure you want to log out?'))
    window.location.href = '/gurong-gabai/logout.php';
}
</script>