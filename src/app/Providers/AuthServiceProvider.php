<?php

namespace App\Providers;

use App\Models\Feed;
use App\Models\LibraryItem;
use App\Models\User;
use App\Policies\FeedPolicy;
use App\Policies\LibraryItemPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Feed::class => FeedPolicy::class,
        LibraryItem::class => LibraryItemPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}
