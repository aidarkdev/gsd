<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users · GSD</title>
    <link rel="stylesheet" href="/static/app.css">
</head>
<body>
    <main>
        <h1>Users</h1>

        <nav>
            <a href="/">Home</a>
            <a href="/dashboard">Dashboard</a>
            <form method="post" action="/logout">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Logout</button>
            </form>
        </nav>

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
</body>
</html>
