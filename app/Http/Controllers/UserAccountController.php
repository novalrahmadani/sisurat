<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserAccountRequest;
use App\Http\Requests\UpdateUserAccountRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class UserAccountController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'role' => ['nullable', Rule::in(['all', User::ROLE_ADMIN, User::ROLE_STAFF])],
        ]);
        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? 'all';
        $role = $filters['role'] ?? 'all';

        return $this->privatePage($request, 'accounts/Index', [
            'accounts' => User::query()->select(['id', 'name', 'email', 'role', 'is_active', 'is_primary_admin', 'created_at'])
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
                }))
                ->when($status !== 'all', fn (Builder $query) => $query->where('is_active', $status === 'active'))
                ->when($role !== 'all', fn (Builder $query) => $query->where('role', $role))
                ->orderByDesc('is_primary_admin')->orderBy('name')->orderBy('id')
                ->paginate(20)->withQueryString()->through(fn (User $account): array => [
                    ...$this->accountData($account),
                    'created_at' => $account->created_at?->toIso8601String(),
                ]),
            'filters' => ['q' => $search, 'status' => $status, 'role' => $role],
            'stats' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'admins' => User::query()->where('is_active', true)->where('role', User::ROLE_ADMIN)->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return $this->privatePage($request, 'accounts/Form', [
            'account' => null,
            'passwordRules' => Password::default()->toPasswordRulesString(),
        ]);
    }

    public function store(StoreUserAccountRequest $request): RedirectResponse
    {
        try {
            (new User)->getConnection()->transaction(function () use ($request): void {
                $accounts = User::query()->orderBy('id')->lockForUpdate()->get();
                $actor = $this->lockedActor($accounts, $request);
                Gate::forUser($actor)->authorize('create', User::class);
                $data = $request->validated();

                $account = new User;
                $account->fill(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
                $account->forceFill([
                    'role' => $data['role'],
                    'is_active' => (bool) $data['is_active'],
                    'is_primary_admin' => false,
                ])->save();
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email ini sudah digunakan oleh akun lain.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun berhasil ditambahkan.']);

        return to_route('accounts.index');
    }

    public function edit(Request $request, User $account): Response
    {
        Gate::authorize('update', $account);

        return $this->privatePage($request, 'accounts/Form', [
            'account' => $this->accountData($account),
            'passwordRules' => Password::default()->toPasswordRulesString(),
        ]);
    }

    public function update(UpdateUserAccountRequest $request, User $account): RedirectResponse
    {
        try {
            (new User)->getConnection()->transaction(function () use ($request, $account): void {
                $accounts = User::query()->orderBy('id')->lockForUpdate()->get();
                $actor = $this->lockedActor($accounts, $request);
                $target = $accounts->find($account->id);
                abort_unless($target instanceof User, 404);
                Gate::forUser($actor)->authorize('update', $target);
                $data = $request->validated();
                $isOwnAccount = (int) $actor->id === (int) $target->id;

                if ($target->is_primary_admin && ($data['role'] !== User::ROLE_ADMIN || ! $data['is_active'])) {
                    throw ValidationException::withMessages(['role' => 'Admin utama harus tetap berperan Admin dan berstatus aktif.']);
                }

                if ($isOwnAccount && ($data['role'] !== $target->role || (bool) $data['is_active'] !== $target->is_active)) {
                    throw ValidationException::withMessages(['role' => 'Anda tidak dapat mengubah peran atau menonaktifkan akun sendiri.']);
                }

                if ($isOwnAccount && filled($data['password'] ?? null)) {
                    throw ValidationException::withMessages(['password' => 'Ubah kata sandi akun Anda melalui menu Keamanan.']);
                }

                $oldEmail = $target->email;
                $target->fill(['name' => $data['name'], 'email' => $data['email']]);
                $target->forceFill(['role' => $data['role'], 'is_active' => (bool) $data['is_active']]);

                if (filled($data['password'] ?? null)) {
                    $target->password = $data['password'];
                }

                if ($target->isDirty('email')) {
                    $target->email_verified_at = null;
                }

                $revokeAccess = $target->isDirty(['email', 'password', 'role', 'is_active']);

                if ($revokeAccess) {
                    $target->remember_token = null;
                }

                $target->save();

                if ($revokeAccess) {
                    $this->revokeSessions($target, $isOwnAccount ? $request->session()->getId() : null);
                    $this->deleteResetTokens([$oldEmail, $target->email]);
                }
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email ini sudah digunakan oleh akun lain.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun berhasil diperbarui.']);

        return to_route('accounts.index');
    }

    /**
     * @param  Collection<int, User>  $accounts
     */
    private function lockedActor(Collection $accounts, Request $request): User
    {
        $actor = $accounts->find($request->user()?->id);
        abort_unless($actor instanceof User && $actor->isAdministrator(), 403);

        return $actor;
    }

    /**
     * @return array{id: int, name: string, email: string, role: string, is_active: bool, is_primary_admin: bool}
     */
    private function accountData(User $account): array
    {
        return [
            'id' => (int) $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'role' => $account->role,
            'is_active' => $account->is_active,
            'is_primary_admin' => $account->is_primary_admin,
        ];
    }

    private function revokeSessions(User $account, ?string $keepSessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $connection = DB::connection(config('session.connection'));
        $table = config('session.table', 'sessions');

        if ($connection->getSchemaBuilder()->hasTable($table)) {
            $connection->table($table)->where('user_id', $account->id)
                ->when($keepSessionId !== null, fn (QueryBuilder $query) => $query->where('id', '!=', $keepSessionId))->delete();
        }
    }

    /**
     * @param  array<int, string>  $emails
     */
    private function deleteResetTokens(array $emails): void
    {
        $broker = config('auth.defaults.passwords');
        $table = config('auth.passwords.'.$broker.'.table');
        $connection = DB::connection(config('auth.passwords.'.$broker.'.connection'));

        if (is_string($table) && $connection->getSchemaBuilder()->hasTable($table)) {
            $connection->table($table)->whereIn('email', array_unique($emails))->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function privatePage(Request $request, string $component, array $props): Response
    {
        $response = Inertia::render($component, $props)->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
