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
    <title><?= htmlspecialchars($t('habits.title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/static/app.css">
    <script src="/static/app.js" defer></script>
    <script type="module" src="/engine/bootstrap.js"></script>
</head>
<body class="app-page">
    <div class="app-shell">
        <?php require BASE_PATH . '/templates/partials/app-header.php'; ?>

        <script type="application/json" id="__BAKED__"><?= json_encode($partsBaked, $jsonFlags) ?></script>
        <script type="application/json" id="__MOUNTS__"><?= json_encode($partsMounts, $jsonFlags) ?></script>

        <main class="app-main habit-main">
            <div data-mount-id="habit-rules">
                <h1><?= htmlspecialchars($t('habits.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="calendar-empty"><?= htmlspecialchars($t('habits.loading'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </main>

        <?php require BASE_PATH . '/templates/partials/app-sidebar.php'; ?>
    </div>
</body>
</html>
