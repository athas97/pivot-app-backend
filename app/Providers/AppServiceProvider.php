<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\HobbyRepositoryInterface;
use App\Repositories\HobbyRepository;
use App\Repositories\Contracts\ChallengePackRepositoryInterface;
use App\Repositories\ChallengePackRepository;
use App\Repositories\Contracts\ActivityRepositoryInterface;
use App\Repositories\ActivityRepository;
use App\Repositories\Contracts\ActivityLogsRepositoryInterface;
use App\Repositories\ActivityLogsRepository;
use App\Repositories\Auth\AuthRepositoryInterface;
use App\Repositories\Contracts\UserHobbyRepositoryInterface;
use App\Repositories\UserHobbyRepository;
use App\Repositories\UserProfileRepository;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use App\Repositories\AuthRepository;

use App\Repositories\PasswordResetRepository;
use App\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Repositories\Auth\LogoutRepositoryInterface;
use App\Repositories\LogoutRepository;
use App\Repositories\Contracts\ResearchRepositoryInterface;
use App\Repositories\ResearchRepository;
use App\Repositories\Contracts\CountriesRepositoryInterface;
use App\Repositories\CountriesRepository;
use App\Repositories\Contracts\AppBlockLogRepositoryInterface;
use App\Repositories\AppBlockLogRepository;
use App\Repositories\Contracts\AppUsageLogsRepositoryInterface;
use App\Repositories\AppUsageLogsRepository;
use App\Repositories\Contracts\FocusSessionLogsRepositoryInterface;
use App\Repositories\FocusSessionLogsRepository;
use App\Repositories\Contracts\ChallengeLogRepositoryInterface;
use App\Repositories\ChallengeLogRepository;
use App\Repositories\Contracts\GoalLogsRepositoryInterface;
use App\Repositories\GoalLogsRepository;
use App\Repositories\Contracts\EmotionLogsRepositoryInterface;
use App\Repositories\EmotionLogsRepository;
use App\Repositories\Contracts\StreakLogsRepositoryInterface;
use App\Repositories\StreakLogsRepository;
use App\Repositories\Contracts\GroupChallengeSessionRepositoryInterface;
use App\Repositories\GroupChallengeSessionRepository;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\EventRepository;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HobbyRepositoryInterface::class, HobbyRepository::class);
        $this->app->bind(ActivityRepositoryInterface::class, ActivityRepository::class);
        $this->app->bind(ActivityLogsRepositoryInterface::class, ActivityLogsRepository::class);
        $this->app->bind(UserHobbyRepositoryInterface::class, UserHobbyRepository::class);
        $this->app->bind(UserProfileRepositoryInterface::class, UserProfileRepository::class);
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(PasswordResetRepositoryInterface::class, PasswordResetRepository::class);
        $this->app->bind(LogoutRepositoryInterface::class, LogoutRepository::class);
        $this->app->bind(ResearchRepositoryInterface::class, ResearchRepository::class);
        $this->app->bind(CountriesRepositoryInterface::class, CountriesRepository::class);
        $this->app->bind(AppBlockLogRepositoryInterface::class, AppBlockLogRepository::class);
        $this->app->bind(AppUsageLogsRepositoryInterface::class, AppUsageLogsRepository::class);
        $this->app->bind(FocusSessionLogsRepositoryInterface::class, FocusSessionLogsRepository::class);
        $this->app->bind(ChallengeLogRepositoryInterface::class, ChallengeLogRepository::class);
        $this->app->bind(GoalLogsRepositoryInterface::class, GoalLogsRepository::class);
        $this->app->bind(EmotionLogsRepositoryInterface::class, EmotionLogsRepository::class);
        $this->app->bind(ChallengePackRepositoryInterface::class, ChallengePackRepository::class);
        $this->app->bind(GroupChallengeSessionRepositoryInterface::class, GroupChallengeSessionRepository::class);
        $this->app->bind(EventRepositoryInterface::class, EventRepository::class);
        $this->app->bind(StreakLogsRepositoryInterface::class, StreakLogsRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perHour(3)
                ->by($request->input('email') . '|' . $request->ip())
                ->response(function () {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Too many requests. Please wait before trying again.',
                    ], 429);
                });
        });
    }
}
