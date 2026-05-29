<?php

declare(strict_types=1);

namespace App\I18n;

final class Translator
{
    private const FALLBACK_LANGUAGE = 'en';

    /**
     * @var array<string, array<string, string>>
     */
    private const STRINGS = [
        'en' => [
            'app.name' => 'GSD',

            'nav.home' => 'Home',
            'nav.login' => 'Login',
            'nav.dashboard' => 'Dashboard',
            'nav.calendar' => 'Calendar',
            'nav.users' => 'Users',
            'nav.logout' => 'Logout',
            'nav.primary' => 'Primary navigation',
            'nav.toggle' => 'Toggle navigation',

            'language.switch_to' => 'RU',

            'home.title' => 'GSD',
            'home.status' => 'Minimal PHP app is running.',

            'auth.login.title' => 'Login · GSD',
            'auth.login.heading' => 'Sign in',
            'auth.login.subtitle' => 'Access your workspace dashboard.',
            'auth.login.failed' => 'Sign-in failed',
            'auth.field.email' => 'Email',
            'auth.field.password' => 'Password',
            'auth.submit.sign_in' => 'Sign in',
            'auth.error.invalid' => 'Email or password is incorrect.',

            'dashboard.title' => 'Dashboard · GSD',
            'dashboard.heading' => 'Dashboard',
            'dashboard.client_clicks' => 'Client clicks',
            'dashboard.count' => 'Count',

            'calendar.title' => 'Calendar · GSD',
            'calendar.heading' => 'Calendar',
            'calendar.controls' => 'Calendar controls',
            'calendar.previous' => 'Earlier',
            'calendar.today' => 'Today',
            'calendar.next' => 'Later',
            'calendar.empty_day' => 'No entries',
            'calendar.day_note' => 'Day note',
            'calendar.marker.recurring' => 'Recurring task',
            'calendar.marker.long' => 'Multi-day task',
            'calendar.attachment.photo' => 'Photo',
            'calendar.attachment.audio' => 'Audio',
            'calendar.status.ongoing' => 'Ongoing',
            'calendar.status.done' => 'Done',
            'calendar.status.will_do' => 'Will do',
            'calendar.status.stale' => 'Stale',
            'calendar.weekday.1' => 'Mon',
            'calendar.weekday.2' => 'Tue',
            'calendar.weekday.3' => 'Wed',
            'calendar.weekday.4' => 'Thu',
            'calendar.weekday.5' => 'Fri',
            'calendar.weekday.6' => 'Sat',
            'calendar.weekday.7' => 'Sun',
            'calendar.month.1' => 'Jan',
            'calendar.month.2' => 'Feb',
            'calendar.month.3' => 'Mar',
            'calendar.month.4' => 'Apr',
            'calendar.month.5' => 'May',
            'calendar.month.6' => 'Jun',
            'calendar.month.7' => 'Jul',
            'calendar.month.8' => 'Aug',
            'calendar.month.9' => 'Sep',
            'calendar.month.10' => 'Oct',
            'calendar.month.11' => 'Nov',
            'calendar.month.12' => 'Dec',

            'admin.users.title' => 'Users · GSD',
            'admin.users.heading' => 'Users',
            'admin.users.col.id' => 'ID',
            'admin.users.col.email' => 'Email',
            'admin.users.col.name' => 'Name',
            'admin.users.col.role' => 'Role',
            'admin.users.col.created' => 'Created',

            'validation.required' => 'Required',
            'validation.email' => 'Invalid email',
            'validation.too_short' => 'Too short',

            'error.forbidden' => '403',
            'error.invalid_csrf' => 'Invalid form token',
            'error.too_many_login_attempts' => 'Too many login attempts',
            'error.not_found' => '404',
            'error.server' => 'Server error',
        ],
        'ru' => [
            'app.name' => 'GSD',

            'nav.home' => 'Главная',
            'nav.login' => 'Войти',
            'nav.dashboard' => 'Панель',
            'nav.calendar' => 'Календарь',
            'nav.users' => 'Пользователи',
            'nav.logout' => 'Выйти',
            'nav.primary' => 'Основная навигация',
            'nav.toggle' => 'Переключить навигацию',

            'language.switch_to' => 'EN',

            'home.title' => 'GSD',
            'home.status' => 'Минимальное PHP-приложение работает.',

            'auth.login.title' => 'Войти · GSD',
            'auth.login.heading' => 'Войти',
            'auth.login.subtitle' => 'Доступ к рабочей панели.',
            'auth.login.failed' => 'Вход не выполнен',
            'auth.field.email' => 'Email',
            'auth.field.password' => 'Пароль',
            'auth.submit.sign_in' => 'Войти',
            'auth.error.invalid' => 'Email или пароль указаны неверно.',

            'dashboard.title' => 'Панель · GSD',
            'dashboard.heading' => 'Панель',
            'dashboard.client_clicks' => 'Клики клиента',
            'dashboard.count' => 'Считать',

            'calendar.title' => 'Календарь · GSD',
            'calendar.heading' => 'Календарь',
            'calendar.controls' => 'Управление календарем',
            'calendar.previous' => 'Раньше',
            'calendar.today' => 'Сегодня',
            'calendar.next' => 'Позже',
            'calendar.empty_day' => 'Нет записей',
            'calendar.day_note' => 'Заметка дня',
            'calendar.marker.recurring' => 'Повторяющаяся задача',
            'calendar.marker.long' => 'Многодневная задача',
            'calendar.attachment.photo' => 'Фото',
            'calendar.attachment.audio' => 'Аудио',
            'calendar.status.ongoing' => 'В работе',
            'calendar.status.done' => 'Готово',
            'calendar.status.will_do' => 'Сделаю',
            'calendar.status.stale' => 'Протухло',
            'calendar.weekday.1' => 'Пн',
            'calendar.weekday.2' => 'Вт',
            'calendar.weekday.3' => 'Ср',
            'calendar.weekday.4' => 'Чт',
            'calendar.weekday.5' => 'Пт',
            'calendar.weekday.6' => 'Сб',
            'calendar.weekday.7' => 'Вс',
            'calendar.month.1' => 'Янв',
            'calendar.month.2' => 'Фев',
            'calendar.month.3' => 'Мар',
            'calendar.month.4' => 'Апр',
            'calendar.month.5' => 'Май',
            'calendar.month.6' => 'Июн',
            'calendar.month.7' => 'Июл',
            'calendar.month.8' => 'Авг',
            'calendar.month.9' => 'Сен',
            'calendar.month.10' => 'Окт',
            'calendar.month.11' => 'Ноя',
            'calendar.month.12' => 'Дек',

            'admin.users.title' => 'Пользователи · GSD',
            'admin.users.heading' => 'Пользователи',
            'admin.users.col.id' => 'ID',
            'admin.users.col.email' => 'Email',
            'admin.users.col.name' => 'Имя',
            'admin.users.col.role' => 'Роль',
            'admin.users.col.created' => 'Создан',

            'validation.required' => 'Обязательное поле',
            'validation.email' => 'Некорректный email',
            'validation.too_short' => 'Слишком коротко',

            'error.forbidden' => '403',
            'error.invalid_csrf' => 'Некорректный токен формы',
            'error.too_many_login_attempts' => 'Слишком много попыток входа',
            'error.not_found' => '404',
            'error.server' => 'Ошибка сервера',
        ],
    ];

    public function __construct(private string $defaultLanguage)
    {
        $this->defaultLanguage = $this->normalize($defaultLanguage);
    }

    public function currentLanguage(): string
    {
        return $this->normalize((string) ($_SESSION['lang'] ?? $this->defaultLanguage));
    }

    public function setCurrentLanguage(string $language): string
    {
        $language = $this->normalize($language);
        $_SESSION['lang'] = $language;

        return $language;
    }

    public function oppositeLanguage(string $language): string
    {
        return $this->normalize($language) === 'ru' ? 'en' : 'ru';
    }

    public function translate(string $language, string $key): string
    {
        $language = $this->normalize($language);

        return self::STRINGS[$language][$key]
            ?? self::STRINGS[self::FALLBACK_LANGUAGE][$key]
            ?? $key;
    }

    private function normalize(string $language): string
    {
        return isset(self::STRINGS[$language]) ? $language : self::FALLBACK_LANGUAGE;
    }
}
