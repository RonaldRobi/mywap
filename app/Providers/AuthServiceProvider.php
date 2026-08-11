<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\UsrahGroup;
use App\Policies\CategoryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UsrahGroupPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Product::class => ProductPolicy::class,
        Category::class => CategoryPolicy::class,
        Order::class => OrderPolicy::class,
        UsrahGroup::class => UsrahGroupPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
