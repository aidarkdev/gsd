<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\I18n\Translator;
use App\View\TemplateRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HomeController
{
    public function __construct(
        private TemplateRenderer $templates,
        private AuthService $auth,
        private CsrfToken $csrf,
        private Translator $translator
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
        $response->getBody()->write($this->templates->render('home.php', [
            'user' => $user,
            'csrfToken' => $this->csrf->get(),
            'lang' => $lang,
            'languageAction' => '/lang/' . $this->translator->oppositeLanguage($lang),
            'languageLabel' => $this->translator->translate($lang, 'language.switch_to'),
            't' => fn (string $key): string => $this->translator->translate($lang, $key),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
