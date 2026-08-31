<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `terms.filler` — WHAT STANDS IN THE FRAME'S HOLE.
     *
     * `terms.frame` («I worked on ___») has been here since v0.2 and answers where the gap is.
     * Under v0.3 the model stopped writing a line's `text` altogether: it returns the frame and the
     * FILLER — «the payment module», copied character for character from that day's own word card —
     * and the server pastes them ({@see \App\Modules\Generation\Application\Service\PlanDayComposer::assemble()}).
     * `text` therefore still holds exactly what it held before, and nothing that reads it changes.
     *
     * The column exists because the pair is not recoverable from the sentence. Given «I worked on
     * the payment module.» and «I worked on ___», the substring in the hole can be re-derived by
     * regex, and that is exactly the sort of derivation that is right nine times and silently wrong
     * the tenth — a filler with a full stop in it, a frame whose fixed part repeats. The session
     * cuts a cloze gap from `frame`, and the thing it blanks is this string; storing it is one
     * column against a class of parsing bug.
     *
     * NULL on everything that is not a plan line with a slot: a formula («Nice to meet you»), a
     * quoted interlocutor line, a word, a connector, and every term that never came from a plan
     * day. Same rule `frame` follows, and for the same reason — an empty string would read as
     * «a hole filled with nothing» to everything downstream.
     */
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->text('filler')->nullable()->after('frame');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn('filler');
        });
    }
};
