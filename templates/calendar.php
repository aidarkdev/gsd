<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($t('calendar.title'), ENT_QUOTES, 'UTF-8') ?></title>
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

        <main class="app-main calendar-main">
            <div class="calendar-topbar">
                <div>
                    <h1><?= htmlspecialchars($t('calendar.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p><?= htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <nav class="calendar-controls" aria-label="<?= htmlspecialchars($t('calendar.controls'), ENT_QUOTES, 'UTF-8') ?>">
                    <a href="<?= htmlspecialchars($previousUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t('calendar.previous'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="/calendar"><?= htmlspecialchars($t('calendar.today'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t('calendar.next'), ENT_QUOTES, 'UTF-8') ?></a>
                </nav>
            </div>

            <div class="calendar-weeks">
                <?php foreach ($weeks as $weekIndex => $week): ?>
                    <section class="calendar-week" aria-labelledby="calendar-week-<?= (int) $weekIndex ?>">
                        <h2 id="calendar-week-<?= (int) $weekIndex ?>"><?= htmlspecialchars($week['label'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="calendar-week-grid">
                            <?php foreach ($week['days'] as $day): ?>
                                <?php
                                $dayClasses = [
                                    'calendar-day',
                                    $day['monthClass'],
                                    $day['isToday'] ? 'is-today' : '',
                                    $day['isWeekend'] ? 'is-weekend' : '',
                                ];
                                ?>
                                <article class="<?= htmlspecialchars(implode(' ', array_filter($dayClasses)), ENT_QUOTES, 'UTF-8') ?>">
                                    <header class="calendar-day-header">
                                        <span><?= htmlspecialchars($day['weekday'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong><?= htmlspecialchars($day['dayNumber'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small><?= htmlspecialchars($day['month'], ENT_QUOTES, 'UTF-8') ?></small>
                                    </header>

                                    <div class="calendar-day-body">
                                        <?php foreach ($day['tasks'] as $task): ?>
                                            <div class="calendar-task calendar-task-<?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') ?>">
                                                <div class="calendar-task-line">
                                                    <?php if ($task['isRecurring']): ?>
                                                        <span class="calendar-marker" title="<?= htmlspecialchars($t('calendar.marker.recurring'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($t('calendar.marker.recurring'), ENT_QUOTES, 'UTF-8') ?>">R</span>
                                                    <?php endif; ?>
                                                    <?php if ($task['isLong']): ?>
                                                        <span class="calendar-marker" title="<?= htmlspecialchars($t('calendar.marker.long'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($t('calendar.marker.long'), ENT_QUOTES, 'UTF-8') ?>">
                                                            <?= $task['isStart'] ? '[' : ($task['isEnd'] ? ']' : '-') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="calendar-task-title"><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                                <span class="calendar-status"><?= htmlspecialchars($t('calendar.status.' . $task['status']), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if ($task['preview'] !== ''): ?>
                                                    <p><?= htmlspecialchars($task['preview'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php endif; ?>
                                                <?php if ($task['attachments'] !== []): ?>
                                                    <div class="calendar-attachments">
                                                        <?php foreach ($task['attachments'] as $attachment): ?>
                                                            <a href="/attachments/<?= (int) $attachment['id'] ?>">
                                                                <?= htmlspecialchars($t('calendar.attachment.' . $attachment['media_type']), ENT_QUOTES, 'UTF-8') ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if ($day['note'] !== null): ?>
                                            <div class="calendar-note">
                                                <strong><?= htmlspecialchars($t('calendar.day_note'), ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php if ($day['note']['preview'] !== ''): ?>
                                                    <p><?= htmlspecialchars($day['note']['preview'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php endif; ?>
                                                <?php if ($day['note']['attachments'] !== []): ?>
                                                    <div class="calendar-attachments">
                                                        <?php foreach ($day['note']['attachments'] as $attachment): ?>
                                                            <a href="/attachments/<?= (int) $attachment['id'] ?>">
                                                                <?= htmlspecialchars($t('calendar.attachment.' . $attachment['media_type']), ENT_QUOTES, 'UTF-8') ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($day['tasks'] === [] && $day['note'] === null): ?>
                                            <p class="calendar-empty"><?= htmlspecialchars($t('calendar.empty_day'), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </main>

        <aside class="app-sidebar" id="app-sidebar" data-app-sidebar>
            <nav class="sidebar-nav" aria-label="<?= htmlspecialchars($t('nav.primary'), ENT_QUOTES, 'UTF-8') ?>">
                <a href="/"><?= htmlspecialchars($t('nav.home'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/dashboard"><?= htmlspecialchars($t('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="/calendar"><?= htmlspecialchars($t('nav.calendar'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="/admin/users"><?= htmlspecialchars($t('nav.users'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
            </nav>
        </aside>
    </div>
</body>
</html>
