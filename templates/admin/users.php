<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($t('admin.users.title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/static/app.css">
    <script src="/static/app.js" defer></script>
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

        <main class="app-main">
            <h1><?= htmlspecialchars($t('admin.users.heading'), ENT_QUOTES, 'UTF-8') ?></h1>

            <table>
                <thead>
                    <tr>
                        <th><?= htmlspecialchars($t('admin.users.col.id'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($t('admin.users.col.email'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($t('admin.users.col.name'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($t('admin.users.col.role'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($t('admin.users.col.created'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $listedUser): ?>
                        <tr>
                            <td><?= (int) $listedUser['id'] ?></td>
                            <td><?= htmlspecialchars($listedUser['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($listedUser['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($listedUser['role'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($listedUser['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>

        <aside class="app-sidebar" id="app-sidebar" data-app-sidebar>
            <nav class="sidebar-nav" aria-label="<?= htmlspecialchars($t('nav.primary'), ENT_QUOTES, 'UTF-8') ?>">
                <a href="/"><?= htmlspecialchars($t('nav.home'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/dashboard"><?= htmlspecialchars($t('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/calendar"><?= htmlspecialchars($t('nav.calendar'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/admin/users"><?= htmlspecialchars($t('nav.users'), ENT_QUOTES, 'UTF-8') ?></a>
            </nav>
        </aside>
    </div>
</body>
</html>
