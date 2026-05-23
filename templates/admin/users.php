<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users · GSD</title>
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
                <span class="visually-hidden">Toggle navigation</span>
            </button>
            <a class="app-logo" href="/">GSD</a>
            <div class="app-header-actions">
                <span class="app-user-email"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></span>
                <form method="post" action="/logout" class="app-header-logout">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="link-action">Logout</button>
                </form>
            </div>
        </header>

        <main class="app-main">
            <h1>Users</h1>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Created</th>
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
            <nav class="sidebar-nav" aria-label="Primary navigation">
                <a href="/">Home</a>
                <a href="/dashboard">Dashboard</a>
                <a href="/admin/users">Users</a>
            </nav>
        </aside>
    </div>
</body>
</html>
