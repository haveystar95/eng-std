<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\ValueObject;

/**
 * WHERE A FOLDER CAME FROM, when it did not come from the learner.
 *
 * Null — which is nearly every folder — means «the learner's own»: they made it, generated it or
 * subscribed to it, and it is a shelf they keep. A value here means the folder is the INSIDE of
 * something else and only that thing should be showing it.
 *
 * `plan` is the only one, and it exists because a plan day owns an ordinary collection on purpose:
 * that is what lets the whole session machinery deal a plan card without knowing plans exist. The
 * cost was that it was ordinary in every LIST too — «Мои коллекции» showed «Ответить на вопросы
 * врача» next to «У врача и в аптеке» (Д-34), and the home screen's word-challenge drew its wrong
 * answers out of the plan's own replies, before and after the plan was archived (Д-35).
 *
 * Not a fourth {@see CollectionType}: the types say who may SEE a folder and are checked in a dozen
 * places, and a plan day is exactly what its type says — a private custom folder of this owner's.
 * This says only where it came from, and each list decides for itself whether that matters.
 */
enum CollectionOrigin: string
{
    case Plan = 'plan';
}
