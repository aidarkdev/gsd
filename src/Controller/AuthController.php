<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
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
        private Validator $validator
    ) {
    }

    public function loginForm(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        return $this->html($response, 'auth/login.php', [
            'csrfToken' => $this->csrf->get(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];
        $errors = $this->validator->validate($data, [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if ($errors === [] && !$this->auth->attempt((string) $data['email'], (string) $data['password'])) {
            $errors['auth'] = 'Email or password is incorrect.';
        }

        if ($errors !== []) {
            return $this->html($response->withStatus(422), 'auth/login.php', [
                'csrfToken' => $this->csrf->get(),
                'errors' => $errors,
                'old' => ['email' => (string) ($data['email'] ?? '')],
            ]);
        }

        return $response
            ->withHeader('Location', '/dashboard')
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
        $response->getBody()->write($this->templates->render($template, $data));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
