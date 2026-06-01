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
