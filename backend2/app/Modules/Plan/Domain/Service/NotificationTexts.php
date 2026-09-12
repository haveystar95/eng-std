<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\NotificationText;

/**
 * THE LETTERS' WORDS — ready to print, in the learner's native language, next to {@see NativeStrings}
 * whose plural rule they reuse («7 дней», «3 дня»). Russian and English are written out; any other
 * language falls back to English rather than to a wrong ending. The Russian lines are the owner's
 * (наряд PLAN-UI-3) and are copied into the client glossary verbatim — change them in both places.
 */
final class NotificationTexts
{
    /** @var array<string, array<string, string>> */
    private const LINES = [
        'ru' => [
            'plan_ready.title' => 'План готов',
            'plan_ready.body' => '{lead}. День 1 — «{title}»',
            'day_ready.title' => 'День {n} собран',
            'day_ready.body' => '«{title}» — можно начинать',
            'daily_reminder.title' => 'День {n} ждёт',
            'daily_reminder.body' => '«{title}» — начни с того места, где остановился',
            'event_today.title' => 'Сегодня {event}',
            'event_today.fallback' => 'Сегодня разговор',
            'event_today.body' => 'Скажи сам перед разговором — прогони его вслух',
            'days_skipped_rebuilt.title' => 'Маршрут пересобран',
            'days_skipped_rebuilt.body' => 'Было {from}, стало {to}',
            'review' => 'Повторение',
            'rehearsal' => 'Репетиция',
        ],
        'en' => [
            'plan_ready.title' => 'Your plan is ready',
            'plan_ready.body' => '{lead}. Day 1 — “{title}”',
            'day_ready.title' => 'Day {n} is ready',
            'day_ready.body' => '“{title}” — you can start',
            'daily_reminder.title' => 'Day {n} is waiting',
            'daily_reminder.body' => '“{title}” — pick up where you left off',
            'event_today.title' => 'Today: {event}',
            'event_today.fallback' => 'Today is the conversation',
            'event_today.body' => 'Say it yourself before the conversation — run it out loud',
            'days_skipped_rebuilt.title' => 'Route rebuilt',
            'days_skipped_rebuilt.body' => 'It was {from}, now {to}',
            'review' => 'Review',
            'rehearsal' => 'Rehearsal',
        ],
    ];

    private readonly NativeStrings $strings;

    public function __construct(private readonly string $lang)
    {
        $this->strings = new NativeStrings($lang);
    }

    /**
     * «План готов» / «До приёма · 7 дней. День 1 — «Регистратура»». `$untilPhrase` is the server's
     * countdown string when the plan has an event date; without one the lead is the length («5 дней»).
     */
    public function planReady(?string $untilPhrase, int $daysTotal, string $day1Title): NotificationText
    {
        $lead = $untilPhrase ?? $this->strings->count($daysTotal, 'day');

        return new NotificationText(
            $this->line('plan_ready.title'),
            $this->fill('plan_ready.body', ['lead' => $lead, 'title' => $day1Title]),
        );
    }

    public function dayReady(int $number, string $title): NotificationText
    {
        return new NotificationText(
            $this->fill('day_ready.title', ['n' => (string) $number]),
            $this->fill('day_ready.body', ['title' => $title]),
        );
    }

    public function dailyReminder(int $number, string $title): NotificationText
    {
        return new NotificationText(
            $this->fill('daily_reminder.title', ['n' => (string) $number]),
            $this->fill('daily_reminder.body', ['title' => $title]),
        );
    }

    /** «Сегодня приём у врача» — the prompt's `event_native` with its first letter lowered. */
    public function eventToday(?string $eventNative): NotificationText
    {
        $event = trim((string) $eventNative);
        $title = $event === ''
            ? $this->line('event_today.fallback')
            : $this->fill('event_today.title', ['event' => mb_strtolower(mb_substr($event, 0, 1)).mb_substr($event, 1)]);

        return new NotificationText($title, $this->line('event_today.body'));
    }

    /** «Было 7 дней, стало 5» — the first number agrees with its noun, the second stands alone. */
    public function daysSkippedRebuilt(int $from, int $to): NotificationText
    {
        return new NotificationText(
            $this->line('days_skipped_rebuilt.title'),
            $this->fill('days_skipped_rebuilt.body', ['from' => $this->strings->count($from, 'day'), 'to' => (string) $to]),
        );
    }

    /** The name a day goes by in a letter: its scene's title, or «Повторение» / «Репетиция». */
    public function dayTitle(DayType $type, ?string $sceneTitle): string
    {
        return match ($type) {
            DayType::Scene => $sceneTitle ?? $this->line('review'),
            DayType::Review => $this->line('review'),
            DayType::Rehearsal => $this->line('rehearsal'),
        };
    }

    private function line(string $key): string
    {
        $table = isset(self::LINES[$this->lang]) ? $this->lang : 'en';

        return self::LINES[$table][$key];
    }

    /** @param array<string, string> $values */
    private function fill(string $key, array $values): string
    {
        $out = $this->line($key);
        foreach ($values as $name => $value) {
            $out = str_replace('{'.$name.'}', $value, $out);
        }

        return $out;
    }
}
