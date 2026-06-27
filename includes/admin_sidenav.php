<?php
$active_nav = $active_nav ?? '';
$initials   = strtoupper(substr($_SESSION['teacher_name'] ?? 'A', 0, 2));
$name       = htmlspecialchars($_SESSION['teacher_name'] ?? 'Admin');
?>
<nav class="sidebar" id="mainSidebar">

  <div class="sb-brand">
    <div class="sb-dot" style="font-size:13px;font-weight:800;color:#fff;">G</div>
    <span class="nav-text" style="font-size:14px;font-weight:700;color:#f1f5f9;letter-spacing:-0.2px;">
      Gurong <em style="color:#60a5fa;font-style:normal;">GabAI</em>
    </span>
  </div>

  <div class="nav-grp">
    <div class="nav-lbl">Admin Panel</div>
    <!-- ORDER: Dashboard → All Accounts → Approvals → Statistics -->
    <a href="/gurong-gabai/modules/admin/dashboard.php" class="nav-a <?= $active_nav==='dashboard'?'on':'' ?>">
      <span class="ico"><i class="bi bi-house"></i></span>
      <span class="nav-text"> Dashboard</span>
    </a>
    <a href="/gurong-gabai/modules/admin/accounts.php" class="nav-a <?= $active_nav==='accounts'?'on':'' ?>">
      <span class="ico"><i class="bi bi-people"></i></span>
      <span class="nav-text"> All Accounts</span>
    </a>
    <a href="/gurong-gabai/modules/admin/approvals.php" class="nav-a <?= $active_nav==='approvals'?'on':'' ?>">
      <span class="ico"><i class="bi bi-person-check"></i></span>
      <span class="nav-text"> Approvals</span>
    </a>
    <a href="/gurong-gabai/modules/admin/statistics.php" class="nav-a <?= $active_nav==='statistics'?'on':'' ?>">
      <span class="ico"><i class="bi bi-bar-chart-line"></i></span>
      <span class="nav-text"> Statistics</span>
    </a>
  </div>

  <div class="nav-grp">
    <div class="nav-lbl">Account</div>
    <a href="#" class="nav-a" onclick="confirmLogout(event)">
      <span class="ico"><i class="bi bi-box-arrow-right"></i></span>
      <span class="nav-text"> Log Out</span>
    </a>
  </div>

  <div class="sb-bottom">
    <button class="collapse-btn" onclick="toggleSidebar()" id="collapseBtn" title="Collapse">
      <span id="collapseIcon"><i class="bi bi-layout-sidebar-reverse"></i></span>
      <span class="nav-text" style="font-size:12px;margin-left:6px;">Collapse</span>
    </button>
    <button class="dark-toggle" onclick="toggleDark()">
      <div class="toggle-track"><div class="toggle-thumb"></div></div>
      <span id="theme-label" class="nav-text">Dark Mode</span>
    </button>
    <div class="user-chip">
      <div class="user-ava" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
        <?= $initials ?>
      </div>
      <div class="nav-text">
        <div class="user-name"><?= $name ?></div>
        <div class="user-role">System Admin</div>
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