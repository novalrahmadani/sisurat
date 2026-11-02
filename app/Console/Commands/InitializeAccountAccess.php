<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('accounts:initialize {--user= : ID akun yang menjadi admin utama} {--preview : Tampilkan rencana tanpa mengubah data}')]
#[Description('Aktifkan satu admin utama dan tutup akses awal akun lain tanpa menghapus data')]
class InitializeAccountAccess extends Command
{
    public function handle(): int
    {
        $selectedId = $this->option('user');

        if (! is_string($selectedId) || ! ctype_digit($selectedId) || (int) $selectedId < 1) {
            $this->error('Tentukan ID admin utama dengan --user=<id>. Tidak ada akun yang diubah.');

            return self::FAILURE;
        }

        $selectedId = (int) $selectedId;

        if ($this->option('preview')) {
            try {
                $accounts = User::query()->orderBy('id')->get();
                $selected = $this->selectedAccount($accounts, $selectedId);
                $hasPrimary = $accounts->contains(fn (User $account): bool => $account->is_primary_admin);

                $this->line(json_encode([
                    'preview' => true,
                    'primary_user_id' => (int) $selected->id,
                    'already_initialized' => $hasPrimary,
                    'accounts_total' => $accounts->count(),
                    'accounts_to_deactivate' => $hasPrimary ? 0 : max(0, $accounts->count() - 1),
                ], JSON_THROW_ON_ERROR));
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        try {
            $result = (new User)->getConnection()->transaction(function () use ($selectedId): array {
                $accounts = User::query()->orderBy('id')->lockForUpdate()->get();
                $selected = $this->selectedAccount($accounts, $selectedId);

                if ($accounts->contains(fn (User $account): bool => $account->is_primary_admin)) {
                    return [
                        'primary_user_id' => (int) $selected->id,
                        'already_initialized' => true,
                        'accounts_total' => $accounts->count(),
                        'accounts_deactivated' => 0,
                    ];
                }

                $otherEmails = [];

                foreach ($accounts as $account) {
                    $isSelected = (int) $account->id === $selectedId;
                    $account->forceFill([
                        'role' => $isSelected ? User::ROLE_ADMIN : User::ROLE_STAFF,
                        'is_active' => $isSelected,
                        'is_primary_admin' => $isSelected,
                    ]);

                    if (! $isSelected) {
                        $account->remember_token = null;
                        $otherEmails[] = $account->email;
                    }

                    $account->save();
                }

                $this->revokeOtherSessions($selectedId);
                $this->deleteOtherResetTokens($otherEmails);

                return [
                    'primary_user_id' => $selectedId,
                    'already_initialized' => false,
                    'accounts_total' => $accounts->count(),
                    'accounts_deactivated' => count($otherEmails),
                ];
            }, 5);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $accounts
     */
    private function selectedAccount(Collection $accounts, int $selectedId): User
    {
        $selected = $accounts->find($selectedId);

        if (! $selected instanceof User) {
            throw new RuntimeException('ID akun admin utama tidak ditemukan. Tidak ada akun yang diubah.');
        }

        $primaryAccounts = $accounts->filter(fn (User $account): bool => $account->is_primary_admin);

        if ($primaryAccounts->count() > 1 || ($primaryAccounts->isNotEmpty() && ! $selected->is_primary_admin)) {
            throw new RuntimeException('Admin utama sudah ditetapkan. Inisialisasi tidak dapat mengganti admin utama.');
        }

        if ($selected->is_primary_admin && ! $selected->isAdministrator()) {
            throw new RuntimeException('Status admin utama tidak konsisten. Tidak ada akun yang diubah.');
        }

        return $selected;
    }

    private function revokeOtherSessions(int $selectedId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $connection = DB::connection(config('session.connection'));
        $table = config('session.table', 'sessions');

        if ($connection->getSchemaBuilder()->hasTable($table)) {
            $connection->table($table)->where('user_id', '!=', $selectedId)->delete();
        }
    }

    /**
     * @param  array<int, string>  $emails
     */
    private function deleteOtherResetTokens(array $emails): void
    {
        if ($emails === []) {
            return;
        }

        $broker = config('auth.defaults.passwords');
        $table = config('auth.passwords.'.$broker.'.table');
        $connection = DB::connection(config('auth.passwords.'.$broker.'.connection'));

        if (is_string($table) && $connection->getSchemaBuilder()->hasTable($table)) {
            foreach (array_chunk(array_unique($emails), 1000) as $emailChunk) {
                $connection->table($table)->whereIn('email', $emailChunk)->delete();
            }
        }
    }
}
