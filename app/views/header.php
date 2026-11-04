<?php
/**
 * Page header and role-based navigation.
 * Expects $pageTitle; optional $activeNav (key of the current menu item).
 *
 * Two layouts:
 *  - Signed-in, verified users: left sidebar (grouped by role) + top header + content.
 *  - Visitors and unverified users: simple public top bar.
 * The sidebar only shows links the role may open; every page still checks the role on the server.
 */
$user = current_user();
$role = $user['role_key'] ?? null;
$verified = $user && is_verified($user);
$activeNav = $activeNav ?? '';
$appLayout = $user && $verified;

// Small counts shown next to menu items, so staff see waiting work at a glance.
$badges = [];
if ($appLayout && $role === ROLE_STAFF) {
    $badges['requests'] = (int) db_value("SELECT COUNT(*) FROM document_requests WHERE status IN ('Pending', 'Under Review') AND is_archived = 0");
    $badges['queue'] = (int) db_value("SELECT COUNT(*) FROM concerns WHERE status IN ('Submitted', 'Under Review') AND assigned_to IS NULL AND is_archived = 0");
} elseif ($appLayout && $role === ROLE_RESIDENT) {
    $badges['requests'] = (int) db_value("SELECT COUNT(*) FROM document_requests WHERE resident_id = ? AND status = 'Ready for Pickup'", [(int) $user['user_id']]);
}

// Sidebar groups: [group label => [key => [path, label, icon]]]. An empty label means no heading.
$groups = [];
if ($appLayout && $role === ROLE_ADMIN) {
    $groups = [
        '' => ['dashboard' => ['admin/dashboard.php', 'Dashboard', 'dashboard']],
        'Records' => [
            'residents' => ['residents.php', 'Residents', 'residents'],
            'requests'  => ['requests.php', 'Document Requests', 'document'],
            'concerns'  => ['concerns.php', 'Community Concerns', 'concern'],
        ],
        'Services' => [
            'doctypes'   => ['admin/document-types.php', 'Document Types', 'services'],
            'categories' => ['admin/categories.php', 'Concern Categories', 'category'],
        ],
        'Reports' => [
            'reports' => ['reports.php', 'Reports', 'reports'],
            'logs'    => ['admin/logs.php', 'Activity Logs', 'logs'],
        ],
        'System' => [
            'users'    => ['admin/users.php', 'User Accounts', 'accounts'],
            'settings' => ['admin/settings.php', 'Settings', 'settings'],
            'profile'  => ['profile.php', 'My Profile', 'profile'],
        ],
    ];
} elseif ($appLayout && $role === ROLE_STAFF) {
    $groups = [
        '' => ['dashboard' => ['staff/dashboard.php', 'Dashboard', 'dashboard']],
        'Work' => [
            'requests' => ['requests.php', 'Document Requests', 'document'],
            'concerns' => ['concerns.php', 'Community Concerns', 'concern'],
            'queue'    => ['staff/queue.php', 'Triage Queue', 'queue'],
        ],
        'Records' => [
            'residents' => ['residents.php', 'Residents', 'residents'],
            'services'  => ['services.php', 'Services', 'services'],
        ],
        'Reports' => ['reports' => ['reports.php', 'Reports', 'reports']],
        'Account' => ['profile' => ['profile.php', 'My Profile', 'profile']],
    ];
} elseif ($appLayout && $role === ROLE_RESIDENT) {
    $groups = [
        '' => ['dashboard' => ['resident/dashboard.php', 'Dashboard', 'dashboard']],
        'My records' => [
            'requests' => ['requests.php', 'My Document Requests', 'document'],
            'concerns' => ['concerns.php', 'My Concerns', 'concern'],
        ],
        'Services' => ['services' => ['services.php', 'Services & Requirements', 'services']],
        'Account' => ['profile' => ['profile.php', 'My Profile', 'profile']],
    ];
}

// Breadcrumb for the top header: "Group / Item".
$crumbGroup = '';
$crumbItem = $pageTitle ?? '';
foreach ($groups as $label => $items) {
    if (isset($items[$activeNav])) {
        $crumbGroup = $label;
        $crumbItem = $items[$activeNav][1];
    }
}

$publicNav = [];
if (!$appLayout) {
    $publicNav = $user
        ? ['verify' => ['auth/verify-notice.php', 'Verify email']]
        : ['home' => ['index.php', 'Home'], 'services' => ['services.php', 'Services & requirements']];
}
$flashes = take_flashes();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($pageTitle ?? 'Home') . ' | ' . app_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="<?= $appLayout ? 'app-body' : 'public-body' ?>">
<a class="skip-link" href="#main">Skip to main content</a>
<?php if ($appLayout): ?>
<div class="app-shell">
  <aside class="sidebar offcanvas-lg offcanvas-start" id="sidebar" tabindex="-1" aria-label="Main navigation">
    <div class="sidebar-brand">
      <a class="brand" href="<?= e(url(home_path_for_role($role))) ?>">
        <?= render_app_mark() ?>
        <span class="brand-text">
          <span class="brand-name"><?= e(app_name()) ?></span>
          <span class="brand-sub">Online barangay services</span>
        </span>
      </a>
      <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
    </div>
    <nav class="sidebar-nav">
      <?php foreach ($groups as $label => $items): ?>
        <?php if ($label !== ''): ?><div class="sidebar-heading"><?= e($label) ?></div><?php endif; ?>
        <ul class="sidebar-list">
          <?php foreach ($items as $key => [$path, $itemLabel, $iconName]): ?>
            <li>
              <a class="sidebar-link<?= $key === $activeNav ? ' active' : '' ?>" <?= $key === $activeNav ? 'aria-current="page"' : '' ?> href="<?= e(url($path)) ?>">
                <?= icon($iconName) ?><span><?= e($itemLabel) ?></span>
                <?php if (!empty($badges[$key])): ?><span class="sidebar-badge" title="Waiting"><?= (int) $badges[$key] ?></span><?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
      <div class="sidebar-user-label">Signed in as</div>
      <div class="sidebar-user-name"><?= e(full_name($user)) ?></div>
      <div class="sidebar-user-role"><?= e($user['role_name']) ?></div>
      <form method="post" action="<?= e(url('auth/logout.php')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-light w-100 mt-2"><?= icon('logout', 'icon icon-sm') ?> Sign out</button>
      </form>
    </div>
  </aside>

  <div class="app-main">
    <header class="topbar">
      <button class="topbar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu"><?= icon('menu') ?></button>
      <nav class="topbar-crumb" aria-label="You are here">
        <?php if ($crumbGroup !== ''): ?><span class="crumb-group"><?= e($crumbGroup) ?></span><span class="crumb-sep" aria-hidden="true">/</span><?php endif; ?>
        <span class="crumb-item"><?= e($crumbItem) ?></span>
      </nav>
      <div class="topbar-user">
        <span class="topbar-user-text"><span class="topbar-user-name"><?= e($user['first_name']) ?></span><span class="topbar-user-role"><?= e($user['role_name']) ?></span></span>
        <a class="avatar" href="<?= e(url('profile.php')) ?>" title="My profile" aria-label="My profile"><?= e(mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1))) ?></a>
      </div>
    </header>
    <main id="main" class="page">
<?php else: ?>
<header class="site-header">
  <nav class="navbar navbar-expand-md navbar-dark" aria-label="Main">
    <div class="container-xl">
      <a class="brand" href="<?= e(url('index.php')) ?>">
        <?= render_app_mark() ?>
        <span class="brand-text">
          <span class="brand-name"><?= e(app_name()) ?></span>
          <span class="brand-sub">Online barangay services</span>
        </span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav me-auto">
          <?php foreach ($publicNav as $key => [$path, $label]): ?>
            <li class="nav-item">
              <a class="nav-link<?= $key === $activeNav ? ' active' : '' ?>" <?= $key === $activeNav ? 'aria-current="page"' : '' ?> href="<?= e(url($path)) ?>"><?= e($label) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="nav-user">
          <?php if ($user): ?>
            <span class="nav-user-name"><?= e(full_name($user)) ?><span class="nav-user-role">Email not verified</span></span>
            <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="d-inline">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-outline-light">Sign out</button>
            </form>
          <?php else: ?>
            <a class="btn btn-sm btn-outline-light<?= $activeNav === 'login' ? ' active' : '' ?>" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
            <a class="btn btn-sm btn-light" href="<?= e(url('auth/register.php')) ?>">Register</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
</header>
<main id="main" class="container-xl page">
<?php endif; ?>
  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible" role="<?= $f['type'] === 'danger' ? 'alert' : 'status' ?>">
      <?= e($f['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
    </div>
  <?php endforeach; ?>
