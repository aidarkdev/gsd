<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\I18n\Translator;
use App\Repository\HabitRepository;
use App\View\TemplateRenderer;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HabitController
{
    public function __construct(
        private TemplateRenderer $templates,
        private AuthService $auth,
        private CsrfToken $csrf,
        private Translator $translator,
        private HabitRepository $habits
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
        $response->getBody()->write($this->templates->render('habits.php', [
            'user' => $user,
            'csrfToken' => $this->csrf->get(),
            'lang' => $lang,
            'languageAction' => '/lang/' . $this->translator->oppositeLanguage($lang),
            'languageLabel' => $this->translator->translate($lang, 'language.switch_to'),
            't' => fn (string $key): string => $this->translator->translate($lang, $key),
            'partsBaked' => [
                'habit-rules' => [
                    'habits' => $this->normalizeHabits($this->habits->findAllForUser((int) $user['id'])),
                    'today' => (new DateTimeImmutable('today'))->format('Y-m-d'),
                    'strings' => $this->strings($lang),
                ],
            ],
            'partsMounts' => [
                'instances' => [
                    [
                        'id' => 'habit-rules',
                        'part' => '/parts/habit-rules/index.js',
                    ],
                ],
            ],
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @param array<int, array<string, mixed>> $habits
     * @return array<int, array<string, mixed>>
     */
    private function normalizeHabits(array $habits): array
    {
        return array_map(static fn (array $habit): array => [
            'id' => (int) $habit['id'],
            'name' => (string) $habit['name'],
            'habit_series_uid' => (string) $habit['habit_series_uid'],
            'frequency_days' => (int) $habit['frequency_days'],
            'mode' => (string) $habit['mode'],
            'start_date' => (string) $habit['start_date'],
            'end_date' => $habit['end_date'] === null ? null : (string) $habit['end_date'],
            'active' => (bool) $habit['active'],
            'created_at' => (string) $habit['created_at'],
            'updated_at' => (string) $habit['updated_at'],
        ], $habits);
    }

    private function strings(string $lang): array
    {
        $keys = [
            'habits.title',
            'habits.heading',
            'habits.loading',
            'habits.empty_active',
            'habits.empty_archive',
            'habits.active',
            'habits.archive',
            'habits.add_habit',
            'habits.save_habit',
            'habits.archive_habit',
            'habits.resume_habit',
            'habits.name_field',
            'habits.frequency_field',
            'habits.mode_field',
            'habits.start_date',
            'habits.period',
            'habits.edit',
            'habits.cancel',
            'habits.created',
            'habits.updated',
            'calendar.workspace.strict',
            'calendar.workspace.sliding',
        ];
        $strings = [];

        foreach ($keys as $key) {
            $strings[$key] = $this->translator->translate($lang, $key);
        }

        return $strings;
    }
}
