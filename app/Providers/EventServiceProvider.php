<?php

namespace App\Providers;

use App\Listeners\OrderStatusEventSubscriber;
use App\Models\AutoAssignment;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;


use App\Models\User;
use App\Observers\UserObserver;
use App\Models\Order;
use App\Models\PackageType;
use App\Models\SubscriptionVendor;
use App\Models\Vehicle;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Service;
use App\Models\TaxiOrder;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Observers\AutoAssignmentObserver;
//
use App\Observers\OrderObserver;
use App\Observers\OrderFeesObserver;
use App\Observers\PackageTypeObserver;
use App\Observers\SubscriptionObserver;
//
use App\Observers\TaxiDriverObserver;
use App\Observers\TaxiOrderObserver;
use App\Observers\TaxiOrderTripObserver;
use App\Observers\VehicleObserver;
use App\Observers\PayoutObserver;
use App\Observers\ProductObserver;
use App\Observers\ServiceObserver;
use App\Observers\VendorObserver;
use App\Observers\ReferralObserver;
use App\Observers\WalletObserver;
use App\Observers\OrderLoyaltyObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    protected $subscribe = [
        OrderStatusEventSubscriber::class,
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
        User::observe(UserObserver::class);
        Vendor::observe(VendorObserver::class);
        Order::observe(OrderObserver::class);
        Order::observe(OrderFeesObserver::class);
        SubscriptionVendor::observe(SubscriptionObserver::class);
        Payout::observe(PayoutObserver::class);

        //Majorly for taxi 
        User::observe(TaxiDriverObserver::class);
        Order::observe(TaxiOrderObserver::class);
        TaxiOrder::observe(TaxiOrderTripObserver::class);
        Vehicle::observe(VehicleObserver::class);
        
        //Subscription qty checks
        Product::observe(ProductObserver::class);
        Service::observe(ServiceObserver::class);
        PackageType::observe(PackageTypeObserver::class);
        //
        Order::observe(ReferralObserver::class);
        Order::observe(OrderLoyaltyObserver::class);
        Wallet::observe(WalletObserver::class);
        AutoAssignment::observe(AutoAssignmentObserver::class);
    }
}
