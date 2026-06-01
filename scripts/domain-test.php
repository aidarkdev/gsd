<?php

declare(strict_types=1);

use App\App\Bootstrap;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Bootstrap::loadEnv(BASE_PATH . '/.env');

$services = Bootstrap::services(BASE_PATH);
$createdFiles = [];
$domainReady = false;

try {
    $pdo = $services->database->connect();

    foreach ([
        'task_instances',
        'task_links',
        'notes',
        'habits',
        'habit_entries',
        'attachments',
    ] as $table) {
        $statement = $pdo->prepare('SELECT to_regclass(:table_name)');
        $statement->execute(['table_name' => $table]);

        if ($statement->fetchColumn() === null) {
            fwrite(STDERR, 'DB domain tests skipped: schema table missing: ' . $table . PHP_EOL);
            echo "Domain tests skipped\n";
            exit(0);
        }
    }

    $pdo->beginTransaction();
    $domainReady = true;

    $userId = $services->users->create(
        'domain-test-' . bin2hex(random_bytes(8)) . '@example.com',
        'Domain Test User',
        'password',
        'user'
    );

    $firstTaskId = $services->tasks->createInstance(
        $userId,
        'Hydrate',
        'Drink water',
        '2026-05-01',
        '2026-05-01',
        'done'
    );

    $longTaskId = $services->tasks->createInstance(
        $userId,
        'Trip',
        'Pack and travel',
        '2026-05-03',
        '2026-05-06',
        'ongoing'
    );

    $childTaskId = $services->tasks->createInstance(
        $userId,
        'Pack camera',
        '',
        '2026-05-03',
        '2026-05-03',
        'will_do',
        $longTaskId
    );

    $services->tasks->linkInstances($userId, $firstTaskId, $longTaskId);
    $inboxTaskId = $services->tasks->createInboxTask($userId, 'Inbox idea', 'No date yet');

    $dayNoteId = $services->notes->createDayNote($userId, '2026-05-03', 'Day note', 'Day note');
    $regularNoteId = $services->notes->createRegularNote($userId, 'Regular note', 'Loose thought');

    $attachmentDir = BASE_PATH . '/storage/attachments/domain-test';

    if (!is_dir($attachmentDir)) {
        mkdir($attachmentDir, 0775, true);
    }

    $taskAttachmentPath = $attachmentDir . '/voice-' . bin2hex(random_bytes(8)) . '.m4a';
    file_put_contents($taskAttachmentPath, 'test audio');
    $createdFiles[] = $taskAttachmentPath;

    $noteAttachmentPath = $attachmentDir . '/photo-' . bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($noteAttachmentPath, 'test photo');
    $createdFiles[] = $noteAttachmentPath;

    $taskAttachmentId = $services->attachments->createForTask(
        $userId,
        $firstTaskId,
        'voice.m4a',
        'storage/attachments/domain-test/' . basename($taskAttachmentPath),
        'audio/mp4',
        'audio',
        1234
    );
    $services->attachments->createForNote(
        $userId,
        $dayNoteId,
        'photo.jpg',
        'storage/attachments/domain-test/' . basename($noteAttachmentPath),
        'image/jpeg',
        'photo',
        4321
    );

    assertCount(3, $services->tasks->findInstancesForRange($userId, '2026-05-01', '2026-05-04'), 'calendar task range');
    assertCount(1, $services->tasks->findInboxTasks($userId), 'inbox task range');
    assertCount(1, $services->notes->findDayNotesForRange($userId, '2026-05-01', '2026-05-04'), 'day note range');
    assertCount(1, $services->attachments->findForTasks($userId, [$firstTaskId, $childTaskId]), 'task attachments');
    assertCount(1, $services->attachments->findForNotes($userId, [$dayNoteId]), 'note attachments');

    $strictHabit = $services->habits->createRule($userId, 'Meditate', 3, 'strict', '2026-05-01');
    $slidingHabit = $services->habits->createRule($userId, 'Run', 4, 'sliding', '2026-04-01');
    if (
        strlen((string) $strictHabit['habit_series_uid']) !== 32
        || (string) $strictHabit['habit_series_uid'] === (string) $slidingHabit['habit_series_uid']
    ) {
        fail('new habits get distinct series uids');
    }
    $services->habits->upsertEntry($userId, (int) $strictHabit['id'], '2026-05-01', 'done');
    $services->habits->upsertEntry($userId, (int) $slidingHabit['id'], '2026-04-28', 'done');
    $habitEntries = $services->habits->findEntriesForRangeWithSlidingLookback($userId, '2026-05-01', '2026-05-10');
    assertAnyRow($habitEntries, 'performed_date', '2026-04-28', 'sliding habits include previous entry before range');

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $versionedHabit = $services->habits->versionRule($userId, (int) $strictHabit['id'], 'Meditate more', 2, 'sliding', $today);
    if ($versionedHabit === null || (int) $versionedHabit['id'] === (int) $strictHabit['id']) {
        fail('habit rule versioning creates a new rule');
    }
    if ((string) $versionedHabit['habit_series_uid'] !== (string) $strictHabit['habit_series_uid']) {
        fail('habit rule versioning keeps the same series uid');
    }
    assertCount(3, $services->habits->findAllForUser($userId), 'all habit rules include active and archive');
    $closedHabit = $services->habits->findForUser($userId, (int) $strictHabit['id']);
    if ($closedHabit === null || (bool) $closedHabit['active'] || $closedHabit['end_date'] === null) {
        fail('habit rule versioning closes old rule');
    }

    $_SESSION = ['user_id' => $userId];
    $requestFactory = new Slim\Psr7\Factory\ServerRequestFactory();
    $calendarResponse = (new App\Controller\CalendarController(
        $services->templates,
        $services->auth,
        $services->csrf,
        $services->translator,
        $services->tasks,
        $services->notes,
        $services->habits,
        $services->attachments
    ))->show(
        $requestFactory->createServerRequest('GET', '/calendar')->withQueryParams(['start' => '2026-05-04']),
        new Slim\Psr7\Response()
    );
    assertStatus($calendarResponse, 200, 'calendar page renders for authenticated user');
    assertBodyContains($calendarResponse, 'calendar-workspace', 'calendar page mounts workspace part');
    assertBodyContains($calendarResponse, '/parts/calendar-workspace/index.js', 'calendar page includes workspace part module');
    assertBodyContains($calendarResponse, 'Hydrate', 'calendar baked state includes task title');
    assertBodyContains($calendarResponse, 'Day note', 'calendar baked state includes day note');
    assertBodyContains($calendarResponse, 'Meditate', 'calendar baked state includes habits');
    assertBodyContains($calendarResponse, '"inboxTasks"', 'calendar baked JSON contains inbox tasks key');
    assertBodyContains($calendarResponse, 'Inbox idea', 'calendar baked state includes inbox task');
    assertBodyNotContains($calendarResponse, 'Apr 27 - May 3', 'calendar page omits per-week date range heading');
    assertBodyNotContains($calendarResponse, '<h2 id="calendar-week-', 'calendar page omits per-week heading');
    assertBodyContains($calendarResponse, '"habits"', 'calendar baked JSON contains habits key');
    assertBodyContains($calendarResponse, '"entries"', 'calendar baked JSON contains entries key');
    assertFileContains(
        BASE_PATH . '/public/parts/calendar-workspace/template.js',
        'calendar-workspace-layout',
        'calendar workspace template includes two-column layout'
    );
    assertFileContains(
        BASE_PATH . '/public/parts/calendar-workspace/template.js',
        'slidePanel',
        'calendar workspace template uses shared slide panel'
    );
    assertFileContains(
        BASE_PATH . '/public/parts/calendar-workspace/template.js',
        'calendar-spoiler',
        'calendar workspace template hides day forms behind spoilers'
    );
    assertFileContains(
        BASE_PATH . '/public/parts/calendar-workspace/template.js',
        'habit-entry-state',
        'calendar workspace template uses three-state habit controls'
    );
    assertFileContains(
        BASE_PATH . '/public/parts/calendar-workspace/template.js',
        "habitStateButton(state, slot, 'scheduled')",
        'calendar workspace habit control includes scheduled state'
    );
    assertFileContains(
        BASE_PATH . '/public/parts/calendar-workspace/template.js',
        "habitStateButton(state, slot, 'done')",
        'calendar workspace habit control includes done state'
    );
    assertFileContains(
        BASE_PATH . '/public/parts/calendar-workspace/template.js',
        "habitStateButton(state, slot, 'skipped')",
        'calendar workspace habit control includes skipped state'
    );
    assertFileContains(
        BASE_PATH . '/public/parts/calendar-workspace/template.js',
        'schedule-inbox-to-day',
        'calendar workspace template includes inbox scheduling action'
    );
    assertFileContains(
        BASE_PATH . '/public/parts/calendar-workspace/handlers.js',
        'schedule-inbox-to-day',
        'calendar workspace handlers schedule inbox tasks to selected day'
    );
    assertFileContains(
        BASE_PATH . '/public/parts/shared/slide-panel.js',
        'slide-panel-backdrop',
        'shared slide panel includes backdrop action'
    );
    assertFileNotContains(
        BASE_PATH . '/public/parts/calendar-workspace/template.js',
        'calendar-day-drawer',
        'calendar workspace no longer uses inline day drawer wrapper'
    );

    $inboxResponse = (new App\Controller\InboxController(
        $services->templates,
        $services->auth,
        $services->csrf,
        $services->translator,
        $services->tasks
    ))->show(
        $requestFactory->createServerRequest('GET', '/inbox'),
        new Slim\Psr7\Response()
    );
    assertStatus($inboxResponse, 200, 'inbox page renders for authenticated user');
    assertBodyContains($inboxResponse, '__BAKED__', 'inbox page includes baked state');
    assertBodyContains($inboxResponse, '__MOUNTS__', 'inbox page includes mounts');
    assertBodyContains($inboxResponse, '/parts/inbox-tasks/index.js', 'inbox page includes inbox part module');
    assertBodyContains($inboxResponse, '/inbox', 'inbox page sidebar contains inbox link');
    assertBodyContains($inboxResponse, 'Inbox idea', 'inbox baked state includes inbox task');

    $habitsResponse = (new App\Controller\HabitController(
        $services->templates,
        $services->auth,
        $services->csrf,
        $services->translator,
        $services->habits
    ))->show(
        $requestFactory->createServerRequest('GET', '/habits'),
        new Slim\Psr7\Response()
    );
    assertStatus($habitsResponse, 200, 'habits page renders for authenticated user');
    assertBodyContains($habitsResponse, '__BAKED__', 'habits page includes baked state');
    assertBodyContains($habitsResponse, '__MOUNTS__', 'habits page includes mounts');
    assertBodyContains($habitsResponse, '/parts/habit-rules/index.js', 'habits page includes habits part module');
    assertBodyContains($habitsResponse, '/habits', 'habits page sidebar contains habits link');
    assertBodyContains($habitsResponse, 'Meditate more', 'habits baked state includes active habit');
    assertBodyContains($habitsResponse, 'Meditate', 'habits baked state includes archived habit');

    $apiController = new App\Controller\CalendarApiController(
        $services->auth,
        $services->tasks,
        $services->notes,
        $services->habits,
        $services->attachments
    );
    $dayDataResponse = $apiController->dayData(
        $requestFactory->createServerRequest('GET', '/api/day-data')->withQueryParams([
            'from' => '2026-05-01',
            'to' => '2026-05-10',
        ]),
        new Slim\Psr7\Response()
    );
    assertStatus($dayDataResponse, 200, 'day data API renders for authenticated user');
    assertBodyContains($dayDataResponse, '2026-04-28', 'day data API includes sliding lookback entry');
    assertBodyContains($dayDataResponse, '"inboxTasks"', 'day data API includes inbox tasks key');

    $habitsApiResponse = $apiController->habits(
        $requestFactory->createServerRequest('GET', '/api/habits'),
        new Slim\Psr7\Response()
    );
    assertStatus($habitsApiResponse, 200, 'habits API renders for authenticated user');
    assertBodyContains($habitsApiResponse, 'Meditate more', 'habits API includes active habit');

    $archivedBefore = $services->habits->findForUser($userId, (int) $strictHabit['id']);
    if ($archivedBefore === null) {
        fail('archived habit exists before resume');
    }
    $resumeResponse = $apiController->resumeHabit(
        $requestFactory
            ->createServerRequest('POST', '/api/habits/' . $strictHabit['id'] . '/resume')
            ->withParsedBody([
                'name' => 'Meditate resumed',
                'frequency_days' => '5',
                'mode' => 'strict',
                'start_date' => $today,
            ]),
        new Slim\Psr7\Response(),
        ['id' => (string) $strictHabit['id']]
    );
    assertStatus($resumeResponse, 201, 'resume habit API creates a new rule');
    $resumePayload = json_decode((string) $resumeResponse->getBody(), true);
    $resumedHabit = is_array($resumePayload) ? ($resumePayload['habit'] ?? null) : null;
    $archivedAfter = $services->habits->findForUser($userId, (int) $strictHabit['id']);
    if (
        !is_array($resumedHabit)
        || (string) $resumedHabit['habit_series_uid'] !== (string) $archivedBefore['habit_series_uid']
        || (string) $resumedHabit['name'] !== 'Meditate resumed'
        || (int) $resumedHabit['frequency_days'] !== 5
        || $archivedAfter === null
        || (int) $resumedHabit['id'] === (int) $strictHabit['id']
        || (bool) $archivedAfter['active']
        || (string) $archivedAfter['end_date'] !== (string) $archivedBefore['end_date']
    ) {
        fail('resuming archived habit creates a new rule without rewriting archive');
    }

    $inboxApiController = new App\Controller\InboxApiController($services->auth, $services->tasks);
    $inboxApiResponse = $inboxApiController->index(
        $requestFactory->createServerRequest('GET', '/api/inbox-tasks'),
        new Slim\Psr7\Response()
    );
    assertStatus($inboxApiResponse, 200, 'inbox API renders for authenticated user');
    assertBodyContains($inboxApiResponse, 'Inbox idea', 'inbox API includes inbox task');

    $scheduled = $services->tasks->scheduleTask($userId, $inboxTaskId, '2026-05-05', '2026-05-05');
    if ($scheduled === null) {
        fail('inbox task can be scheduled');
    }
    assertCount(0, $services->tasks->findInboxTasks($userId), 'scheduled inbox task leaves inbox');
    assertCount(2, $services->tasks->findInstancesForRange($userId, '2026-05-05', '2026-05-05'), 'scheduled inbox task enters calendar range');

    $attachmentController = new App\Controller\AttachmentController($services->attachments, $services->auth);
    $ownedAttachmentResponse = $attachmentController->show(
        $requestFactory->createServerRequest('GET', '/attachments/' . $taskAttachmentId),
        new Slim\Psr7\Response(),
        ['id' => (string) $taskAttachmentId]
    );
    assertStatus($ownedAttachmentResponse, 200, 'owned attachment can be streamed');
    assertHeader($ownedAttachmentResponse, 'Content-Type', 'audio/mp4', 'owned attachment content type');

    $missingAttachmentResponse = $attachmentController->show(
        $requestFactory->createServerRequest('GET', '/attachments/999999999'),
        new Slim\Psr7\Response(),
        ['id' => '999999999']
    );
    assertStatus($missingAttachmentResponse, 404, 'missing attachment returns 404');

    $otherUserId = $services->users->create(
        'domain-test-other-' . bin2hex(random_bytes(8)) . '@example.com',
        'Other Domain Test User',
        'password',
        'user'
    );
    $_SESSION = ['user_id' => $otherUserId];
    $foreignAttachmentResponse = $attachmentController->show(
        $requestFactory->createServerRequest('GET', '/attachments/' . $taskAttachmentId),
        new Slim\Psr7\Response(),
        ['id' => (string) $taskAttachmentId]
    );
    assertStatus($foreignAttachmentResponse, 404, 'foreign attachment returns 404');

    assertConstraintFails(
        $pdo,
        'INSERT INTO task_instances (user_id, title, start_date, end_date, status)
         VALUES (:user_id, \'Bad status\', \'2026-05-01\', \'2026-05-01\', \'maybe\')',
        ['user_id' => $userId],
        'invalid task status'
    );
    assertConstraintFails(
        $pdo,
        'INSERT INTO task_instances (user_id, title, start_date, status)
         VALUES (:user_id, \'Half scheduled\', \'2026-05-01\', \'will_do\')',
        ['user_id' => $userId],
        'task cannot have only one date'
    );
    assertConstraintFails(
        $pdo,
        'INSERT INTO notes (user_id, note_type, body_md)
         VALUES (:user_id, \'day\', \'Missing date\')',
        ['user_id' => $userId],
        'day note without date'
    );
    assertConstraintFails(
        $pdo,
        'INSERT INTO notes (user_id, note_type, note_date, body_md)
         VALUES (:user_id, \'day\', \'2026-05-03\', \'Duplicate day note\')',
        ['user_id' => $userId],
        'duplicate day note'
    );
    assertConstraintFails(
        $pdo,
        'INSERT INTO habits (user_id, name, habit_series_uid, frequency_days, mode, start_date)
         VALUES (:user_id, \'Bad habit\', :series_uid, 0, \'strict\', \'2026-05-01\')',
        ['user_id' => $userId, 'series_uid' => str_repeat('a', 32)],
        'invalid habit frequency'
    );
    assertConstraintFails(
        $pdo,
        'INSERT INTO habits (user_id, name, habit_series_uid, frequency_days, mode, start_date)
         VALUES (:user_id, \'Bad mode\', :series_uid, 1, \'loose\', \'2026-05-01\')',
        ['user_id' => $userId, 'series_uid' => str_repeat('b', 32)],
        'invalid habit mode'
    );
    assertConstraintFails(
        $pdo,
        'INSERT INTO habits (user_id, name, habit_series_uid, frequency_days, mode, start_date)
         VALUES (:user_id, \'Bad series\', \'not-a-series\', 1, \'strict\', \'2026-05-01\')',
        ['user_id' => $userId],
        'invalid habit series uid'
    );
    assertConstraintFails(
        $pdo,
        'INSERT INTO habit_entries (user_id, habit_id, performed_date, status)
         VALUES (:user_id, :habit_id, \'2026-05-01\', \'maybe\')',
        ['user_id' => $userId, 'habit_id' => $slidingHabit['id']],
        'invalid habit entry status'
    );
    assertConstraintFails(
        $pdo,
        'INSERT INTO habit_entries (user_id, habit_id, performed_date, status)
         VALUES (:user_id, :habit_id, \'2026-04-28\', \'done\')',
        ['user_id' => $userId, 'habit_id' => $slidingHabit['id']],
        'duplicate habit entry'
    );
    $slotTable = $pdo->query('SELECT to_regclass(\'habit_slots\')')->fetchColumn();
    if ($slotTable !== null) {
        fail('computed habit slots must not be persisted');
    }
    $pdo->rollBack();
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    cleanupFiles($createdFiles);

    if ($domainReady) {
        fwrite(STDERR, 'DB domain tests failed: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    fwrite(STDERR, 'DB domain tests skipped: ' . $exception->getMessage() . PHP_EOL);
    echo "Domain tests skipped\n";
    exit(0);
}

cleanupFiles($createdFiles);
echo "Domain tests passed\n";

function assertCount(int $expected, array $rows, string $message): void
{
    if (count($rows) !== $expected) {
        fail($message . ': expected ' . $expected . ', got ' . count($rows));
    }
}

function assertStatus(Psr\Http\Message\ResponseInterface $response, int $expected, string $message): void
{
    if ($response->getStatusCode() !== $expected) {
        fail($message . ': expected ' . $expected . ', got ' . $response->getStatusCode());
    }
}

function assertHeader(Psr\Http\Message\ResponseInterface $response, string $name, string $expected, string $message): void
{
    if ($response->getHeaderLine($name) !== $expected) {
        fail($message . ': expected ' . $expected . ', got ' . $response->getHeaderLine($name));
    }
}

function assertBodyContains(Psr\Http\Message\ResponseInterface $response, string $needle, string $message): void
{
    if (!str_contains((string) $response->getBody(), $needle)) {
        fail($message . ': missing ' . $needle);
    }
}

function assertBodyNotContains(Psr\Http\Message\ResponseInterface $response, string $needle, string $message): void
{
    if (str_contains((string) $response->getBody(), $needle)) {
        fail($message . ': unexpected ' . $needle);
    }
}

function assertBodySubstringCount(
    Psr\Http\Message\ResponseInterface $response,
    string $needle,
    int $expected,
    string $message
): void {
    $actual = substr_count((string) $response->getBody(), $needle);

    if ($actual !== $expected) {
        fail($message . ': expected ' . $expected . ', got ' . $actual);
    }
}

function assertFileContains(string $path, string $needle, string $message): void
{
    $contents = (string) file_get_contents($path);

    if (!str_contains($contents, $needle)) {
        fail($message . ': missing ' . $needle);
    }
}

function assertFileNotContains(string $path, string $needle, string $message): void
{
    $contents = (string) file_get_contents($path);

    if (str_contains($contents, $needle)) {
        fail($message . ': unexpected ' . $needle);
    }
}

function assertAnyRow(array $rows, string $field, string $expected, string $message): void
{
    foreach ($rows as $row) {
        if ((string) ($row[$field] ?? '') === $expected) {
            return;
        }
    }

    fail($message . ': missing ' . $expected);
}

function assertConstraintFails(PDO $pdo, string $sql, array $params, string $message): void
{
    $pdo->exec('SAVEPOINT expected_failure');

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
    } catch (Throwable) {
        $pdo->exec('ROLLBACK TO SAVEPOINT expected_failure');
        $pdo->exec('RELEASE SAVEPOINT expected_failure');
        return;
    }

    $pdo->exec('RELEASE SAVEPOINT expected_failure');
    fail($message . ': expected constraint failure');
}

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function cleanupFiles(array $files): void
{
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
