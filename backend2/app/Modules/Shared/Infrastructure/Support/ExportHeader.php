<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Support;

use Illuminate\Support\Facades\Process;

/**
 * THE EXPORT HEADER, in one place: every file we hand a human for proof-reading dates itself.
 *
 * An export is a frozen snapshot that goes on looking authoritative forever. The store5 review was
 * written against a file taken 1.5 hours before the fix it then reported as outstanding, and nothing
 * in the file said when it was taken — so nobody could have known. Both halves are needed and for
 * different reasons: the timestamp dates the DATA, the revision dates the CODE that shaped it.
 *
 * It lives here, and not as a private method on whichever command wrote the first export, because a
 * rule that has to be re-typed per command is a rule the second command gets wrong. The machine
 * comment goes FIRST, literally before anything a reader's eye lands on, so two exports can be
 * ordered by a script without parsing prose.
 */
final readonly class ExportHeader
{
    public function __construct(public string $snapshot, public string $head) {}

    public static function now(): self
    {
        return new self(now()->toIso8601String(), self::headRevision());
    }

    /** The machine-readable first line. */
    public function comment(): string
    {
        return "<!-- snapshot: {$this->snapshot} · head: {$this->head} -->";
    }

    /** The same two facts in the rendered document, for the person the rule protects. */
    public function line(string $suffix = ''): string
    {
        return "Снимок: **{$this->snapshot}** · HEAD: `{$this->head}`" . ($suffix !== '' ? " · {$suffix}" : '') . '.';
    }

    /**
     * The commit the export was produced by, short form.
     *
     * `APP_GIT_SHA` first, because that is the only thing that works in a deployed container: the app
     * image mounts backend2, and `.git` lives one directory above it, so asking git in-container finds
     * nothing. The git call is the local-checkout path. When neither answers, the field says so out
     * loud rather than silently disappearing — a header with a hole in it still dates the data, and a
     * reader can see which half is missing.
     */
    public static function headRevision(): string
    {
        $configured = config('services.generation.git_sha');
        if (is_string($configured) && trim($configured) !== '') {
            return substr(trim($configured), 0, 12);
        }

        $result = Process::path(base_path())->run('git rev-parse --short HEAD');

        return $result->successful() && trim($result->output()) !== ''
            ? trim($result->output())
            : 'не определён (нет .git и APP_GIT_SHA)';
    }
}
