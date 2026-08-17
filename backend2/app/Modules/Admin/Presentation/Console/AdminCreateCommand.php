<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Console;

use App\Modules\Admin\Application\Port\AdminRegistrar;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Create a back-office admin. Password is prompted (hidden); if none is entered a strong one is
 * generated and printed once. App users are never touched — this writes only to the `admins` table.
 */
final class AdminCreateCommand extends Command
{
    protected $signature = 'admin:create {email : login email} {--name= : display name}';

    protected $description = 'Create a back-office admin (admin panel login)';

    public function handle(AdminRegistrar $registrar): int
    {
        $emailArg = $this->argument('email');
        $email = is_string($emailArg) ? trim($emailArg) : '';
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email is required.');

            return self::FAILURE;
        }

        $nameOpt = $this->option('name');
        $name = is_string($nameOpt) && trim($nameOpt) !== '' ? trim($nameOpt) : $email;

        $entered = (string) $this->secret('Password (leave blank to auto-generate)');
        $generated = $entered === '';
        $password = $generated ? Str::password(16) : $entered;

        $id = $registrar->create($email, $name, $password);
        if ($id === null) {
            $this->error("An admin with email {$email} already exists.");

            return self::FAILURE;
        }

        $this->info("Created admin {$name} <{$email}> (id {$id}).");
        if ($generated) {
            $this->warn("Generated password (shown once): {$password}");
        }

        return self::SUCCESS;
    }
}
