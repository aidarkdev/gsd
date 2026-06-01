<aside class="app-sidebar" id="app-sidebar" data-app-sidebar>
    <nav class="sidebar-nav" aria-label="<?= htmlspecialchars($t('nav.primary'), ENT_QUOTES, 'UTF-8') ?>">
        <a href="/"><?= htmlspecialchars($t('nav.home'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="/dashboard"><?= htmlspecialchars($t('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="/inbox"><?= htmlspecialchars($t('nav.inbox'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="/habits"><?= htmlspecialchars($t('nav.habits'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="/calendar"><?= htmlspecialchars($t('nav.calendar'), ENT_QUOTES, 'UTF-8') ?></a>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="/admin/users"><?= htmlspecialchars($t('nav.users'), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
    </nav>
</aside>
