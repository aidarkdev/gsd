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
        'task_series',
        'task_instances',
        'task_links',
        'tags',
        'notes',
        'task_tags',
        'note_tags',
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

    $seriesId = $services->tasks->createSeries(
        $userId,
        'Hydrate',
        'Drink water',
        '2026-05-01',
        null,
        2,
        'day'
    );

    $firstTaskId = $services->tasks->createInstance(
        $userId,
        'Hydrate',
        'Drink water',
        '2026-05-01',
        '2026-05-01',
        'done',
        $seriesId
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
        null,
        $longTaskId
    );

    $services->tasks->linkInstances($userId, $firstTaskId, $longTaskId);

    $tagId = $services->tags->findOrCreate($userId, 'Health', 'health');
    $services->tags->addToTask($userId, $firstTaskId, $tagId);

    $dayNoteId = $services->notes->createDayNote($userId, '2026-05-03', 'Day note', 'Day note');
    $regularNoteId = $services->notes->createRegularNote($userId, 'Regular note', 'Loose thought');
    $services->tags->addToNote($userId, $regularNoteId, $tagId);

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
    assertCount(1, $services->notes->findDayNotesForRange($userId, '2026-05-01', '2026-05-04'), 'day note range');
    assertCount(1, $services->attachments->findForTasks($userId, [$firstTaskId, $childTaskId]), 'task attachments');
    assertCount(1, $services->attachments->findForNotes($userId, [$dayNoteId]), 'note attachments');

    $_SESSION = ['user_id' => $userId];
    $requestFactory = new Slim\Psr7\Factory\ServerRequestFactory();
    $calendarResponse = (new App\Controller\CalendarController(
        $services->templates,
        $services->auth,
        $services->csrf,
        $services->translator,
        $services->tasks,
        $services->notes,
        $services->attachments
    ))->show(
        $requestFactory->createServerRequest('GET', '/calendar')->withQueryParams(['start' => '2026-05-04']),
        new Slim\Psr7\Response()
    );
    assertStatus($calendarResponse, 200, 'calendar page renders for authenticated user');
    assertBodyContains($calendarResponse, 'Hydrate', 'calendar page includes task title');
    assertBodyContains($calendarResponse, 'Day note', 'calendar page includes day note');

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
        'INSERT INTO task_series (user_id, title, starts_on, interval_count, interval_unit)
         VALUES (:user_id, \'Bad interval\', \'2026-05-01\', 1, \'hour\')',
        ['user_id' => $userId],
        'invalid recurrence unit'
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
        'INSERT INTO task_instances (user_id, series_id, title, start_date, end_date, status)
         VALUES (:user_id, :series_id, \'Duplicate recurring task\', \'2026-05-01\', \'2026-05-01\', \'will_do\')',
        ['user_id' => $userId, 'series_id' => $seriesId],
        'duplicate recurring task instance'
    );

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
