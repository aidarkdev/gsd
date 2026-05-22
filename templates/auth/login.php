<?php

$email = htmlspecialchars((string) ($old['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$emailError = isset($errors['email']) ? (string) $errors['email'] : null;
$passwordError = isset($errors['password']) ? (string) $errors['password'] : null;
$hasErrors = $errors !== [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · GSD</title>
    <link rel="stylesheet" href="/static/app.css">
</head>
<body class="auth-page">
    <main class="auth-shell" aria-labelledby="login-title">
        <section class="auth-panel">
            <a class="auth-brand" href="/">GSD</a>

            <div class="auth-header">
                <h1 id="login-title">Sign in</h1>
                <p>Access your workspace dashboard.</p>
            </div>

            <?php if ($hasErrors): ?>
                <div class="form-message error" role="alert" id="login-errors">
                    <strong>Sign-in failed</strong>
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/login" class="auth-form"<?= $hasErrors ? ' aria-describedby="login-errors"' : '' ?>>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="field">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="<?= $email ?>"
                        autocomplete="username"
                        required
                        autofocus
                        <?= $emailError !== null ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>
                    >
                    <?php if ($emailError !== null): ?>
                        <p class="field-error" id="email-error"><?= htmlspecialchars($emailError, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        <?= $passwordError !== null ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>
                    >
                    <?php if ($passwordError !== null): ?>
                        <p class="field-error" id="password-error"><?= htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="primary-action">Sign in</button>
            </form>
        </section>
    </main>
</body>
</html>
