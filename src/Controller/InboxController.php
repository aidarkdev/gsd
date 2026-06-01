<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\I18n\Translator;
use App\Repository\TaskRepository;
use App\View\TemplateRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class InboxController
{
    public function __construct(
        private TemplateRenderer $templates,
        private AuthService $auth,
        private CsrfToken $csrf,
        private Translator $translator,
        private TaskRepository $tasks
    ) {
    }

    public function show(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $lang = $this->translator->currentLanguage();
        $response->getBody()->write($this->templates->render('inbox.php', [
            'user' => $user,
            'csrfToken' => $this->csrf->get(),
            'lang' => $lang,
            'languageAction' => '/lang/' . $this->translator->oppositeLanguage($lang),
            'languageLabel' => $this->translator->translate($lang, 'language.switch_to'),
            't' => fn (string $key): string => $this->translator->translate($lang, $key),
            'partsBaked' => [
                'inbox-tasks' => [
                    'tasks' => $this->normalizeTasks($this->tasks->findInboxTasks((int) $user['id'])),
                    'strings' => $this->strings($lang),
                ],
            ],
            'partsMounts' => [
                'instances' => [
                    [
                        'id' => 'inbox-tasks',
                        'part' => '/parts/inbox-tasks/index.js',
                    ],
                ],
            ],
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function normalizeTasks(array $tasks): array
    {
        return array_map(static fn (array $task): array => [
            'id' => (int) $task['id'],
            'title' => (string) $task['title'],
            'body_md' => (string) $task['body_md'],
            'status' => (string) $task['status'],
            'created_at' => (string) $task['created_at'],
            'updated_at' => (string) $task['updated_at'],
        ], $tasks);
    }

    private function strings(string $lang): array
    {
        $keys = [
            'inbox.title',
            'inbox.heading',
            'inbox.empty',
            'inbox.add_task',
            'inbox.save_task',
            'inbox.delete_task',
            'inbox.schedule_task',
            'inbox.title_field',
            'inbox.details_field',
            'inbox.status_field',
            'inbox.start_date',
            'inbox.end_date',
            'inbox.edit',
            'inbox.cancel',
            'inbox.scheduled',
            'calendar.status.ongoing',
            'calendar.status.done',
            'calendar.status.will_do',
            'calendar.status.stale',
        ];
        $strings = [];

        foreach ($keys as $key) {
            $strings[$key] = $this->translator->translate($lang, $key);
        }

        return $strings;
    }
}
