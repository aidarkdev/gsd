<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($t('home.title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/static/app.css">
    <script src="/static/app.js" defer></script>
</head>
<body class="app-page">
    <div class="app-shell">
        <?php require BASE_PATH . '/templates/partials/app-header.php'; ?>

        <main class="app-main">
            <h1><?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($t('home.status'), ENT_QUOTES, 'UTF-8') ?></p>
        </main>

        <?php require BASE_PATH . '/templates/partials/app-sidebar.php'; ?>
    </div>
</body>
</html>
