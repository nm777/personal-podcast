<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin {email : The email address of the user to make admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user an administrator';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->info("User '{$user->name}' ({$user->email}) is already an administrator.");

            return self::SUCCESS;
        }

        $user->update([
            'is_admin' => true,
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->info("User '{$user->name}' ({$user->email}) has been made an administrator and approved.");

        return self::SUCCESS;
    }
}
