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
        <?php require BASE_PATH . '/templates/partials/app-header.php'; ?>

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

        <?php require BASE_PATH . '/templates/partials/app-sidebar.php'; ?>
    </div>
</body>
</html>
