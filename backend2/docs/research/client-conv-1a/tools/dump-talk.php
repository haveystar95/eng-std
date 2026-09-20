<?php

declare(strict_types=1);

/**
 * CLIENT-CONV-1a · СНЯТЬ ФИКСТУРУ РАЗГОВОРА — тот же документ, что отдаёт `GET
 * /plans/{id}/conversation/{cid}`, из журнала одноразовой базы. Ничего не покупает и ничего не пишет.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app \
 *     php docs/research/client-conv-1a/tools/dump-talk.php <conversation-id>
 *
 * Печатает JSON в stdout — его и кладут в `docs/fixtures/conversation-*.json`.
 */

use App\Modules\Plan\Application\Query\GetConversation;
use App\Modules\Plan\Application\Query\GetConversationHandler;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "ОТКАЗ: фикстуры снимаются только с одноразовой базы, а не с «{$database}».\n");
    exit(1);
}

$id = $_SERVER['argv'][1] ?? '';
$row = DB::table('conversations')->where('id', $id)->first();
if ($row === null) {
    fwrite(STDERR, "Нет такого разговора: {$id}\n");
    exit(1);
}
$plan = DB::table('plans')->where('id', $row->plan_id)->first();

$view = app(GetConversationHandler::class)(new GetConversation(new ConversationId($id), new UserId($plan->user_id)));
echo json_encode(PlanJson::conversation($view), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
