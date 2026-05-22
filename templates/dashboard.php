<?php

$jsonFlags = JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Dashboard · GSD</title>
    <link rel="stylesheet" href="/static/app.css">
    <script type="module" src="/engine/bootstrap.js"></script>
</head>
<body>
    <main>
        <h1>Dashboard</h1>

        <script type="application/json" id="__BAKED__"><?= json_encode($partsBaked, $jsonFlags) ?></script>
        <script type="application/json" id="__MOUNTS__"><?= json_encode($partsMounts, $jsonFlags) ?></script>

        <div data-mount-id="dashboard-summary">
            <p><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <nav>
            <a href="/">Home</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="/admin/users">Users</a>
            <?php endif; ?>
            <form method="post" action="/logout">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Logout</button>
            </form>
        </nav>
    </main>
</body>
</html>
