<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\RefusesProductionEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Milestone 09.1 — the two known accounts a developer signs in with.
 *
 * Three deliberate properties:
 *
 *   The seeder refuses to run outside an approved development environment, so these accounts
 *   cannot appear in a real deployment even if someone runs `db:seed --class=...` there.
 *
 *   The passwords are read from DEV_ADMIN_PASSWORD and DEV_USER_PASSWORD and are never given a
 *   fallback. A well-known default like "password" on a machine that later gets exposed is a
 *   real vulnerability, so a missing variable is an error, not a silent default. The values are
 *   hashed by the model's `hashed` cast and are never written to output or to a log.
 *
 *   Rows are keyed by email, so rerunning updates the existing account rather than creating a
 *   second one. `role` is not mass-assignable — by design, so no request can grant it — and is
 *   therefore set with forceFill here, which is the same narrow exception UserFactory uses.
 */
class DevelopmentAccountSeeder extends Seeder
{
    use RefusesProductionEnvironment;

    public const ADMIN_EMAIL = 'admin@tax-simulator.local';

    public const MEMBER_EMAIL = 'user@tax-simulator.local';

    public function run(): void
    {
        $this->assertDevelopmentEnvironment();

        $this->account(self::ADMIN_EMAIL, 'ผู้ดูแลระบบทดสอบ', User::ROLE_ADMIN, 'admin_password', 'DEV_ADMIN_PASSWORD');
        $this->account(self::MEMBER_EMAIL, 'ผู้ใช้งานทดสอบ', User::ROLE_MEMBER, 'member_password', 'DEV_USER_PASSWORD');

        $this->command?->info('Development accounts are present. Passwords were taken from the environment and are not printed.');
    }

    private function account(string $email, string $name, string $role, string $key, string $variable): void
    {
        $password = $this->password($key, $variable);

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        // Hash::make rather than the cast, so the value never sits in an attribute as plain text.
        $user->password = Hash::make($password);
        $user->email_verified_at ??= now();
        $user->save();

        // `role` is intentionally absent from the fillable list; a seeder is the one place
        // besides the factory that may set it, and it does so explicitly.
        if ($user->role !== $role) {
            $user->forceFill(['role' => $role])->save();
        }
    }

    private function password(string $key, string $variable): string
    {
        $value = config('seeding.development_accounts.'.$key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                $variable.' is not set. Development accounts take their passwords from the environment; '
                .'there is deliberately no default. Set '.$variable.' in your .env file and run the seeder again.'
            );
        }

        return $value;
    }
}
