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
            'nav.inbox' => 'Inbox',
            'nav.habits' => 'Habits',
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

            'inbox.title' => 'Inbox · GSD',
            'inbox.heading' => 'Inbox',
            'inbox.loading' => 'Loading inbox',
            'inbox.empty' => 'No inbox tasks',
            'inbox.add_task' => 'Add task',
            'inbox.save_task' => 'Save task',
            'inbox.delete_task' => 'Delete',
            'inbox.schedule_task' => 'Schedule',
            'inbox.title_field' => 'Title',
            'inbox.details_field' => 'Details',
            'inbox.status_field' => 'Status',
            'inbox.start_date' => 'Start date',
            'inbox.end_date' => 'End date',
            'inbox.edit' => 'Edit',
            'inbox.cancel' => 'Cancel',
            'inbox.scheduled' => 'Scheduled',

            'habits.title' => 'Habits · GSD',
            'habits.heading' => 'Habits',
            'habits.loading' => 'Loading habits',
            'habits.empty_active' => 'No active habits',
            'habits.empty_archive' => 'No archived habits',
            'habits.active' => 'Active',
            'habits.archive' => 'Archive',
            'habits.add_habit' => 'Add habit',
            'habits.save_habit' => 'Save habit',
            'habits.archive_habit' => 'Archive',
            'habits.resume_habit' => 'Resume',
            'habits.name_field' => 'Name',
            'habits.frequency_field' => 'Every N days',
            'habits.mode_field' => 'Mode',
            'habits.start_date' => 'Start date',
            'habits.period' => 'Period',
            'habits.edit' => 'Edit',
            'habits.cancel' => 'Cancel',
            'habits.created' => 'Created',
            'habits.updated' => 'Updated',

            'calendar.title' => 'Calendar · GSD',
            'calendar.heading' => 'Calendar',
            'calendar.controls' => 'Calendar controls',
            'calendar.previous' => 'Earlier',
            'calendar.today' => 'Today',
            'calendar.next' => 'Later',
            'calendar.week_number' => 'Week',
            'calendar.workspace.tasks' => 'Tasks',
            'calendar.workspace.habits' => 'Habits',
            'calendar.workspace.note' => 'Note',
            'calendar.workspace.add_task' => 'Add task',
            'calendar.workspace.save_task' => 'Save task',
            'calendar.workspace.delete_task' => 'Delete',
            'calendar.workspace.add_habit' => 'Add habit',
            'calendar.workspace.save_habit' => 'Save habit',
            'calendar.workspace.archive_habit' => 'Archive habit',
            'calendar.workspace.save_note' => 'Save note',
            'calendar.workspace.done' => 'Done',
            'calendar.workspace.skipped' => 'Skipped',
            'calendar.workspace.clear' => 'Clear',
            'calendar.workspace.close' => 'Close',
            'calendar.workspace.title' => 'Title',
            'calendar.workspace.details' => 'Details',
            'calendar.workspace.frequency' => 'Every N days',
            'calendar.workspace.mode' => 'Mode',
            'calendar.workspace.strict' => 'Strict',
            'calendar.workspace.sliding' => 'Sliding',
            'calendar.workspace.start_date' => 'Start date',
            'calendar.workspace.no_habits' => 'No habits',
            'calendar.workspace.loading' => 'Loading calendar',
            'calendar.empty_day' => 'No entries',
            'calendar.day_note' => 'Day note',
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
            'nav.inbox' => 'Инбокс',
            'nav.habits' => 'Привычки',
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

            'inbox.title' => 'Инбокс · GSD',
            'inbox.heading' => 'Инбокс',
            'inbox.loading' => 'Инбокс загружается',
            'inbox.empty' => 'Нет задач без даты',
            'inbox.add_task' => 'Добавить задачу',
            'inbox.save_task' => 'Сохранить задачу',
            'inbox.delete_task' => 'Удалить',
            'inbox.schedule_task' => 'Запланировать',
            'inbox.title_field' => 'Название',
            'inbox.details_field' => 'Детали',
            'inbox.status_field' => 'Статус',
            'inbox.start_date' => 'Дата начала',
            'inbox.end_date' => 'Дата окончания',
            'inbox.edit' => 'Редактировать',
            'inbox.cancel' => 'Отмена',
            'inbox.scheduled' => 'Запланировано',

            'habits.title' => 'Привычки · GSD',
            'habits.heading' => 'Привычки',
            'habits.loading' => 'Привычки загружаются',
            'habits.empty_active' => 'Нет активных привычек',
            'habits.empty_archive' => 'Нет архивных привычек',
            'habits.active' => 'Активные',
            'habits.archive' => 'Архив',
            'habits.add_habit' => 'Добавить привычку',
            'habits.save_habit' => 'Сохранить привычку',
            'habits.archive_habit' => 'Архивировать',
            'habits.resume_habit' => 'Возобновить',
            'habits.name_field' => 'Название',
            'habits.frequency_field' => 'Каждые N дней',
            'habits.mode_field' => 'Режим',
            'habits.start_date' => 'Дата старта',
            'habits.period' => 'Период',
            'habits.edit' => 'Редактировать',
            'habits.cancel' => 'Отмена',
            'habits.created' => 'Создано',
            'habits.updated' => 'Обновлено',

            'calendar.title' => 'Календарь · GSD',
            'calendar.heading' => 'Календарь',
            'calendar.controls' => 'Управление календарем',
            'calendar.previous' => 'Раньше',
            'calendar.today' => 'Сегодня',
            'calendar.next' => 'Позже',
            'calendar.week_number' => 'Неделя',
            'calendar.workspace.tasks' => 'Задачи',
            'calendar.workspace.habits' => 'Привычки',
            'calendar.workspace.note' => 'Заметка',
            'calendar.workspace.add_task' => 'Добавить задачу',
            'calendar.workspace.save_task' => 'Сохранить задачу',
            'calendar.workspace.delete_task' => 'Удалить',
            'calendar.workspace.add_habit' => 'Добавить привычку',
            'calendar.workspace.save_habit' => 'Сохранить привычку',
            'calendar.workspace.archive_habit' => 'Архивировать',
            'calendar.workspace.save_note' => 'Сохранить заметку',
            'calendar.workspace.done' => 'Готово',
            'calendar.workspace.skipped' => 'Пропуск',
            'calendar.workspace.clear' => 'Очистить',
            'calendar.workspace.close' => 'Закрыть',
            'calendar.workspace.title' => 'Название',
            'calendar.workspace.details' => 'Детали',
            'calendar.workspace.frequency' => 'Каждые N дней',
            'calendar.workspace.mode' => 'Режим',
            'calendar.workspace.strict' => 'Строгий',
            'calendar.workspace.sliding' => 'Скользящий',
            'calendar.workspace.start_date' => 'Дата старта',
            'calendar.workspace.no_habits' => 'Нет привычек',
            'calendar.workspace.loading' => 'Календарь загружается',
            'calendar.empty_day' => 'Нет записей',
            'calendar.day_note' => 'Заметка дня',
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
