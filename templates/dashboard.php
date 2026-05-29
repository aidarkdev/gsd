<?php

$jsonFlags = JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT;
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($t('dashboard.title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/static/app.css">
    <script src="/static/app.js" defer></script>
    <script type="module" src="/engine/bootstrap.js"></script>
</head>
<body class="app-page">
    <div class="app-shell">
        <header class="app-header">
            <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false">
                <span class="sidebar-toggle-line"></span>
                <span class="sidebar-toggle-line"></span>
                <span class="sidebar-toggle-line"></span>
                <span class="visually-hidden"><?= htmlspecialchars($t('nav.toggle'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <a class="app-logo" href="/"><?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></a>
            <div class="app-header-actions">
                <span class="app-user-email"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></span>
                <form method="post" action="<?= htmlspecialchars($languageAction, ENT_QUOTES, 'UTF-8') ?>" class="app-header-logout">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="link-action"><?= htmlspecialchars($languageLabel, ENT_QUOTES, 'UTF-8') ?></button>
                </form>
                <form method="post" action="/logout" class="app-header-logout">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="link-action"><?= htmlspecialchars($t('nav.logout'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </header>

        <script type="application/json" id="__BAKED__"><?= json_encode($partsBaked, $jsonFlags) ?></script>
        <script type="application/json" id="__MOUNTS__"><?= json_encode($partsMounts, $jsonFlags) ?></script>

        <main class="app-main">
            <h1><?= htmlspecialchars($t('dashboard.heading'), ENT_QUOTES, 'UTF-8') ?></h1>

            <div data-mount-id="dashboard-summary">
                <p><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </main>

        <aside class="app-sidebar" id="app-sidebar" data-app-sidebar>
            <nav class="sidebar-nav" aria-label="<?= htmlspecialchars($t('nav.primary'), ENT_QUOTES, 'UTF-8') ?>">
                <a href="/"><?= htmlspecialchars($t('nav.home'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/dashboard"><?= htmlspecialchars($t('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/calendar"><?= htmlspecialchars($t('nav.calendar'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="/admin/users"><?= htmlspecialchars($t('nav.users'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
            </nav>
        </aside>
    </div>
</body>
</html>
