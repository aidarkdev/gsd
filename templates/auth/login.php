<?php

$email = htmlspecialchars((string) ($old['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$emailError = isset($errors['email']) ? (string) $errors['email'] : null;
$passwordError = isset($errors['password']) ? (string) $errors['password'] : null;
$hasErrors = $errors !== [];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($t('auth.login.title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/static/app.css">
</head>
<body class="auth-page">
    <main class="auth-shell" aria-labelledby="login-title">
        <section class="auth-panel">
            <div class="auth-topbar">
                <a class="auth-brand" href="/"><?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></a>
                <form method="post" action="<?= htmlspecialchars($languageAction, ENT_QUOTES, 'UTF-8') ?>" class="language-switch">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="language-button"><?= htmlspecialchars($languageLabel, ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>

            <div class="auth-header">
                <h1 id="login-title"><?= htmlspecialchars($t('auth.login.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars($t('auth.login.subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <?php if ($hasErrors): ?>
                <div class="form-message error" role="alert" id="login-errors">
                    <strong><?= htmlspecialchars($t('auth.login.failed'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/login" class="auth-form"<?= $hasErrors ? ' aria-describedby="login-errors"' : '' ?>>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="field">
                    <label for="email"><?= htmlspecialchars($t('auth.field.email'), ENT_QUOTES, 'UTF-8') ?></label>
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
                    <label for="password"><?= htmlspecialchars($t('auth.field.password'), ENT_QUOTES, 'UTF-8') ?></label>
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

                <button type="submit" class="primary-action"><?= htmlspecialchars($t('auth.submit.sign_in'), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        </section>
    </main>
</body>
</html>
