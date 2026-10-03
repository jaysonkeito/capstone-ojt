<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Default password policy: an intern still on their default password logs
 * in with their last name (the login form accepts it in any casing — the
 * submitted value is normalized to the stored last name before the hash
 * check). The hash, however, must match the CURRENTLY stored last name:
 * when a last name is corrected after provisioning (roster re-import,
 * spelling/casing fix), the old hash silently stops matching and the
 * intern is locked out.
 *
 * This command re-anchors those defaults to the current last name. Interns
 * who picked their own password (password_changed_at set) are left alone
 * unless --all is passed, which forcibly returns every intern whose hash
 * no longer matches their last name to the default — their self-chosen
 * password is replaced and the "change your password" nudge returns.
 */
class SyncInternDefaultPasswords extends Command
{
    protected $signature = 'ojt:sync-intern-passwords
        {--dry-run : Only report the interns that would be reset}
        {--all : Also reset interns who picked their own password}';

    protected $description = "Ensure interns on the default password can log in with their current last name (any casing)";

    public function handle(): int
    {
        $interns = User::query()
            ->where('role', 'intern')
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('password_changed_at'))
            ->orderBy('last_name')
            ->get();

        $broken = $interns->filter(fn (User $intern) => ! Hash::check($intern->last_name, $intern->password));

        if ($broken->isEmpty()) {
            $this->info("All {$interns->count()} interns match their last name — nothing to do.");

            return self::SUCCESS;
        }

        foreach ($broken as $intern) {
            $wasChosen = $intern->password_changed_at !== null;

            $this->line(
                "{$intern->student_id} — {$intern->full_name}: "
                .($wasChosen
                    ? 'self-chosen password replaced with the default (their last name).'
                    : "default password no longer matches \"{$intern->last_name}\"; resetting it."),
            );
        }

        if ($this->option('dry-run')) {
            $this->warn("{$broken->count()} intern(s) would be reset (dry run — nothing changed).");

            return self::SUCCESS;
        }

        foreach ($broken as $intern) {
            // The `hashed` cast hashes the plain last name on save, so the
            // stored hash matches the stored casing exactly — which is what
            // the login form's case-insensitive normalization checks against.
            // Returning a self-chosen password to the default also brings
            // back the "change your password" nudge on their next login.
            $intern->update([
                'password' => $intern->last_name,
                'password_changed_at' => null,
            ]);
        }

        $this->info("{$broken->count()} intern(s) reset to their last name.");

        return self::SUCCESS;
    }
}
