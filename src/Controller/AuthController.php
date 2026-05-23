<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\I18n\Translator;
use App\Validation\Validator;
use App\View\TemplateRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private TemplateRenderer $templates,
        private AuthService $auth,
        private CsrfToken $csrf,
        private Translator $translator,
        private Validator $validator
    ) {
    }

    public function loginForm(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        return $this->html($response, 'auth/login.php', [
            'errors' => [],
            'old' => [],
        ]);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $lang = $this->translator->currentLanguage();
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];
        $errors = $this->validator->validate($data, [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if ($errors === [] && !$this->auth->attempt((string) $data['email'], (string) $data['password'])) {
            $errors['auth'] = 'auth.error.invalid';
        }

        if ($errors !== []) {
            return $this->html($response->withStatus(422), 'auth/login.php', [
                'errors' => $this->translateErrors($errors, $lang),
                'old' => ['email' => (string) ($data['email'] ?? '')],
            ]);
        }

        return $response
            ->withHeader('Location', '/dashboard')
            ->withStatus(302);
    }

    public function language(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $this->translator->setCurrentLanguage((string) ($args['code'] ?? ''));

        return $response
            ->withHeader('Location', $this->redirectBackPath($request))
            ->withStatus(302);
    }

    public function logout(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        $this->auth->logout();

        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }

    private function html(ResponseInterface $response, string $template, array $data): ResponseInterface
    {
        $lang = $this->translator->currentLanguage();
        $data += [
            'csrfToken' => $this->csrf->get(),
            'lang' => $lang,
            'languageAction' => '/lang/' . $this->translator->oppositeLanguage($lang),
            'languageLabel' => $this->translator->translate($lang, 'language.switch_to'),
            't' => fn (string $key): string => $this->translator->translate($lang, $key),
        ];

        $response->getBody()->write($this->templates->render($template, $data));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function redirectBackPath(ServerRequestInterface $request): string
    {
        $fallback = $this->auth->check() ? '/dashboard' : '/login';
        $referer = $request->getHeaderLine('Referer');

        if ($referer === '') {
            return $fallback;
        }

        if (str_starts_with($referer, '/') && !str_starts_with($referer, '//')) {
            return $referer;
        }

        $parts = parse_url($referer);

        if (!is_array($parts)) {
            return $fallback;
        }

        $uri = $request->getUri();
        $host = (string) ($parts['host'] ?? '');
        $scheme = (string) ($parts['scheme'] ?? '');
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $path = (string) ($parts['path'] ?? '/');

        if ($host !== $uri->getHost() || ($scheme !== '' && $scheme !== $uri->getScheme())) {
            return $fallback;
        }

        if ($port !== null && $port !== $uri->getPort()) {
            return $fallback;
        }

        if (!str_starts_with($path, '/')) {
            return $fallback;
        }

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * @param array<string, string> $errors
     * @return array<string, string>
     */
    private function translateErrors(array $errors, string $lang): array
    {
        $translated = [];

        foreach ($errors as $field => $key) {
            $translated[$field] = $this->translator->translate($lang, $key);
        }

        return $translated;
    }
}
