<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('manage-internships', fn (User $user): bool => $user->isAdministrator());
        RateLimiter::for('internship-registration', fn (Request $request): array => [Limit::perMinute(10)->by('internship-register-'.$request->ip()), Limit::perHour(30)->by('internship-register-hour-'.$request->ip())]);
        RateLimiter::for('internship-pin', function (Request $request): array {
            $identity = implode('|', array_map(
                static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                [$request->ip(), $request->input('participant_category'), $request->input('institution'), $request->input('student_number')],
            ));

            $identityKey = hash('sha256', $identity);

            return [
                Limit::perMinute(60)->by('internship-pin-ip-'.$request->ip()),
                Limit::perMinute(8)->by('internship-pin-identity-'.$identityKey),
                Limit::perHour(30)->by('internship-pin-hourly-'.$identityKey),
            ];
        });
        RateLimiter::for('internship-pin-issuance', fn (Request $request): array => [
            Limit::perMinute(5)->by('internship-pin-issue-ip-'.$request->ip()),
            Limit::perMinute(5)->by('internship-pin-issue-session-'.hash('sha256', $request->session()->getId())),
        ]);
        RateLimiter::for('internship-verification', fn (Request $request): array => [
            Limit::perMinute(90)->by('internship-ip-'.$request->ip()),
            Limit::perMinute(8)->by('internship-id-'.hash('sha256', $request->ip().'|'.(is_string($request->input('student_number')) ? trim($request->input('student_number')) : ''))),
        ]);
        RateLimiter::for('internship-submissions', fn (Request $request): Limit => Limit::perMinute(15)->by('internship-submit-'.$request->session()->getId()));

        RateLimiter::for('attendance-submissions', fn (Request $request): Limit => Limit::perMinute(15)->by(hash('sha256', $request->session()->getId().'|'.$request->route('token')))
        );

        RateLimiter::for('quran-submissions', fn (Request $request): array => [
            Limit::perMinute(20)->by('mq-session-'.hash('sha256', $request->session()->getId().'|'.$request->route('token'))),
            Limit::perMinute(120)->by('mq-ip-'.hash('sha256', $request->ip().'|'.$request->route('token'))),
        ]);

        RateLimiter::for('interview-submissions', fn (Request $request): Limit => Limit::perMinute(10)->by(hash('sha256', $request->session()->getId().'|'.$request->route('token')))
        );

        RateLimiter::for('pmb-prospect-submissions', fn (Request $request): Limit => Limit::perMinute(8)->by(hash('sha256', $request->ip().'|'.$request->session()->getId())));

    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
