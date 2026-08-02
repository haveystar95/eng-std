<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for the triage vertical slice: a "Triage Demo" collection of ~60 brand-new terms
 * (words + phrases, CEFR spread up to C1/C2 so a B1 profile trips the planner's "risky" branch).
 * ~60 is deliberate: above the queue cap of 40, so the `remaining` field has a real remainder to show.
 *
 * Attaches to EXISTING users — sign in on the device first (which creates your user), then run
 * `php artisan db:seed --class=TriageDemoSeeder`. Idempotent: skips a user who already has it.
 */
final class TriageDemoSeeder extends Seeder
{
    private const COLLECTION_TITLE = 'Triage Demo';

    public function run(): void
    {
        $users = User::query()->get();
        if ($users->isEmpty()) {
            $this->command?->warn('No users yet. Sign in on the device first, then re-run this seeder.');

            return;
        }

        foreach ($users as $user) {
            $this->seedFor($user);
        }
    }

    private function seedFor(User $user): void
    {
        $alreadySeeded = DB::table('collections')
            ->where('owner_id', $user->id)
            ->where('title', self::COLLECTION_TITLE)
            ->exists();
        if ($alreadySeeded) {
            $this->command?->info("Triage Demo already present for {$user->id} — skipping.");

            return;
        }

        $actor = UserId::fromString($user->id);
        $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
            ownerId: $actor,
            title: self::COLLECTION_TITLE,
            sourceLang: new LanguageCode('ru'),
            targetLang: new LanguageCode('en'),
            description: 'Vertical-slice demo — ~60 new terms for the triage screen.',
        ));

        foreach (self::terms() as $term) {
            $termId = app(ImportTermHandler::class)(new ImportTerm(
                lang: new LanguageCode('en'),
                text: $term['text'],
                type: $term['type'],
                pos: null,
                source: 'curated',
                translations: [new TranslationInput(new LanguageCode('ru'), $term['ru'], true)],
                ipa: null,
                examples: isset($term['ex']) ? [new ExampleInput($term['ex'], $term['exru'] ?? null)] : [],
                cefr: $term['cefr'],
            ));
            app(AddTermToCollectionHandler::class)(new AddTermToCollection($collectionId, $termId, $actor));
        }

        $this->command?->info(sprintf('Seeded "%s" (%d terms) for %s.', self::COLLECTION_TITLE, count(self::terms()), $user->id));
    }

    /**
     * @return list<array{text: string, ru: string, type: string, cefr: string, ex?: string, exru?: string}>
     */
    private static function terms(): array
    {
        return [
            // Basic vocabulary the user likely already knows (A1/A2) — the stuff triage should sift out.
            ['text' => 'money', 'ru' => 'деньги', 'type' => 'word', 'cefr' => 'A1'],
            ['text' => 'bank', 'ru' => 'банк', 'type' => 'word', 'cefr' => 'A1'],
            ['text' => 'card', 'ru' => 'карта', 'type' => 'word', 'cefr' => 'A1'],
            ['text' => 'cash', 'ru' => 'наличные', 'type' => 'word', 'cefr' => 'A2'],
            ['text' => 'account', 'ru' => 'счёт', 'type' => 'word', 'cefr' => 'A2'],
            ['text' => 'coin', 'ru' => 'монета', 'type' => 'word', 'cefr' => 'A2'],
            ['text' => 'price', 'ru' => 'цена', 'type' => 'word', 'cefr' => 'A1'],
            ['text' => 'to pay', 'ru' => 'платить', 'type' => 'word', 'cefr' => 'A1'],
            ['text' => 'to save', 'ru' => 'копить', 'type' => 'word', 'cefr' => 'A2'],
            ['text' => 'salary', 'ru' => 'зарплата', 'type' => 'word', 'cefr' => 'A2'],
            ['text' => 'bill', 'ru' => 'счёт (к оплате)', 'type' => 'word', 'cefr' => 'A2'],
            ['text' => 'change', 'ru' => 'сдача', 'type' => 'word', 'cefr' => 'A2'],

            // Mid-level (B1/B2).
            ['text' => 'loan', 'ru' => 'кредит', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'interest', 'ru' => 'проценты', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'to withdraw', 'ru' => 'снимать', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'to deposit', 'ru' => 'вносить (на счёт)', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'balance', 'ru' => 'баланс', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'exchange rate', 'ru' => 'обменный курс', 'type' => 'phrase', 'cefr' => 'B1'],
            ['text' => 'debt', 'ru' => 'долг', 'type' => 'word', 'cefr' => 'B2'],
            ['text' => 'mortgage', 'ru' => 'ипотека', 'type' => 'word', 'cefr' => 'B2'],
            ['text' => 'invoice', 'ru' => 'счёт-фактура', 'type' => 'word', 'cefr' => 'B2'],
            ['text' => 'to afford', 'ru' => 'позволить себе', 'type' => 'word', 'cefr' => 'B2'],

            // Above a B1 profile (C1/C2) — these should trip the "risky" branch on a "known" swipe.
            ['text' => 'overdraft', 'ru' => 'овердрафт', 'type' => 'word', 'cefr' => 'C1'],
            ['text' => 'collateral', 'ru' => 'залог (обеспечение)', 'type' => 'word', 'cefr' => 'C1'],
            ['text' => 'liquidity', 'ru' => 'ликвидность', 'type' => 'word', 'cefr' => 'C1'],
            ['text' => 'to reconcile', 'ru' => 'сверять (счета)', 'type' => 'word', 'cefr' => 'C2'],
            ['text' => 'compound interest', 'ru' => 'сложные проценты', 'type' => 'phrase', 'cefr' => 'C1'],
            ['text' => 'depreciation', 'ru' => 'амортизация', 'type' => 'word', 'cefr' => 'C2'],

            // Phrases (with examples) — half the corpus, and the ones where a fast swipe is telling.
            ['text' => 'to withdraw cash', 'ru' => 'снять наличные', 'type' => 'phrase', 'cefr' => 'B1',
                'ex' => 'I need to withdraw cash from my account.', 'exru' => 'Мне нужно снять наличные со счёта.'],
            ['text' => 'to open an account', 'ru' => 'открыть счёт', 'type' => 'phrase', 'cefr' => 'A2',
                'ex' => 'I would like to open an account.', 'exru' => 'Я хотел бы открыть счёт.'],
            ['text' => 'to pay in instalments', 'ru' => 'платить в рассрочку', 'type' => 'phrase', 'cefr' => 'B2',
                'ex' => 'Can I pay in instalments?', 'exru' => 'Можно платить в рассрочку?'],
            ['text' => 'to take out a loan', 'ru' => 'взять кредит', 'type' => 'phrase', 'cefr' => 'B1',
                'ex' => 'They took out a loan to buy a car.', 'exru' => 'Они взяли кредит на машину.'],
            ['text' => 'to make ends meet', 'ru' => 'сводить концы с концами', 'type' => 'phrase', 'cefr' => 'C1',
                'ex' => "It's hard to make ends meet these days.", 'exru' => 'Сейчас трудно сводить концы с концами.'],
            ['text' => 'to break even', 'ru' => 'выйти в ноль', 'type' => 'phrase', 'cefr' => 'C1',
                'ex' => 'The business finally broke even.', 'exru' => 'Бизнес наконец вышел в ноль.'],
            ['text' => 'could you break this into smaller bills', 'ru' => 'можете разменять помельче', 'type' => 'phrase', 'cefr' => 'B2',
                'ex' => 'Could you break this into smaller bills?', 'exru' => 'Можете разменять это помельче?'],

            // --- Second batch: brings the set to ~60 so the queue cap (40) leaves a real remainder
            //     to test. Same shape: word/phrase mix, and several C1/C2 terms for the risky branch.
            ['text' => 'wallet', 'ru' => 'кошелёк', 'type' => 'word', 'cefr' => 'A1'],
            ['text' => 'receipt', 'ru' => 'чек (квитанция)', 'type' => 'word', 'cefr' => 'A2'],
            ['text' => 'budget', 'ru' => 'бюджет', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'income', 'ru' => 'доход', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'expense', 'ru' => 'расход', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'tax', 'ru' => 'налог', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'currency', 'ru' => 'валюта', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'profit', 'ru' => 'прибыль', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'savings', 'ru' => 'сбережения', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'fine', 'ru' => 'штраф', 'type' => 'word', 'cefr' => 'B1'],
            ['text' => 'fee', 'ru' => 'комиссия', 'type' => 'word', 'cefr' => 'B2'],
            ['text' => 'refund', 'ru' => 'возврат средств', 'type' => 'word', 'cefr' => 'B2'],
            ['text' => 'wage', 'ru' => 'заработок (почасовой)', 'type' => 'word', 'cefr' => 'B2'],

            // More C1/C2 words — extra fuel for the risky branch on a B1 profile.
            ['text' => 'dividend', 'ru' => 'дивиденд', 'type' => 'word', 'cefr' => 'C1'],
            ['text' => 'inflation', 'ru' => 'инфляция', 'type' => 'word', 'cefr' => 'C1'],
            ['text' => 'remittance', 'ru' => 'денежный перевод', 'type' => 'word', 'cefr' => 'C1'],
            ['text' => 'arrears', 'ru' => 'задолженность', 'type' => 'word', 'cefr' => 'C2'],
            ['text' => 'solvency', 'ru' => 'платёжеспособность', 'type' => 'word', 'cefr' => 'C2'],
            ['text' => 'creditworthiness', 'ru' => 'кредитоспособность', 'type' => 'word', 'cefr' => 'C2'],

            // More phrases (with examples).
            ['text' => 'to check the balance', 'ru' => 'проверить баланс', 'type' => 'phrase', 'cefr' => 'A2',
                'ex' => 'Let me check the balance first.', 'exru' => 'Дай сначала проверю баланс.'],
            ['text' => 'to make a deposit', 'ru' => 'сделать вклад', 'type' => 'phrase', 'cefr' => 'B1',
                'ex' => 'I want to make a deposit today.', 'exru' => 'Я хочу сегодня сделать вклад.'],
            ['text' => 'to cut costs', 'ru' => 'сокращать расходы', 'type' => 'phrase', 'cefr' => 'B2',
                'ex' => 'The company had to cut costs.', 'exru' => 'Компании пришлось сокращать расходы.'],
            ['text' => 'to go bankrupt', 'ru' => 'обанкротиться', 'type' => 'phrase', 'cefr' => 'B2',
                'ex' => 'The shop went bankrupt last year.', 'exru' => 'Магазин обанкротился в прошлом году.'],
            ['text' => 'to live beyond your means', 'ru' => 'жить не по средствам', 'type' => 'phrase', 'cefr' => 'C1',
                'ex' => 'They live beyond their means.', 'exru' => 'Они живут не по средствам.'],
            ['text' => 'to tighten your belt', 'ru' => 'затянуть пояса', 'type' => 'phrase', 'cefr' => 'C1',
                'ex' => 'We need to tighten our belts this month.', 'exru' => 'В этом месяце нужно затянуть пояса.'],
        ];
    }
}
