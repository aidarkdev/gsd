<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GSD</title>
    <link rel="stylesheet" href="/static/app.css">
</head>
<body>
    <main>
        <h1>GSD</h1>
        <p>Minimal PHP app is running.</p>

        <nav>
            <?php if ($user === null): ?>
                <a href="/login">Login</a>
            <?php else: ?>
                <a href="/dashboard">Dashboard</a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="/admin/users">Users</a>
                <?php endif; ?>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit">Logout</button>
                </form>
            <?php endif; ?>
        </nav>
    </main>

</body>
</html>
