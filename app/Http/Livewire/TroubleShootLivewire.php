<?php

namespace App\Http\Livewire;

use App\Models\Order;
use App\Models\User;
use App\Traits\FirebaseAuthTrait;
use GeoSot\EnvEditor\Facades\EnvEditor;
//
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use App\Upgrades\Upgrade26;

class TroubleShootLivewire extends BaseLivewireComponent
{

    use FirebaseAuthTrait;

    public $autoAssignmentChecks = [
        "cron_job" => null,
        "online_regular_drivers" => null,
        "online_taxi_drivers" => null,
        "ready_orders" => null,
        "pending_taxi_orders" => null,
        "firebase_drivers" => null,
    ];

    public function render()
    {
        return view('livewire.troubleshoot');
    }


    public function fixImage()
    {

        try {
            //set the domain
            $url = url('');
            $envUrl = env('APP_URL');
            //
            if ($url != $envUrl) {
                if (EnvEditor::keyExists("APP_URL")) {
                    EnvEditor::editKey("APP_URL", $url);
                } else {
                    EnvEditor::addKey("APP_URL", $url);
                }
            }

            //artisan storage link
            \Artisan::call('storage:link', []);
            $this->showSuccessAlert(__("Fix Image(Not Loading)") . " " . __("Successfully"));
        } catch (\Exception $ex) {
            $this->showErrorAlert($ex->getMessage() ?? __("Failed"));
        }
    }

    public function fixCache()
    {

        try {
            //artisan calls
            \Artisan::call('view:clear', []);
            \Artisan::call('config:clear', []);
            \Artisan::call('cache:clear', []);
            $this->showSuccessAlert(__("Clear Cache") . " " . __("Successfully"));
        } catch (\Exception $ex) {
            $this->showErrorAlert($ex->getMessage() ?? __("Failed"));
        }
    }


    public function fixNotification()
    {
        try {
            if (!Schema::hasColumn('push_notifications', 'product_id') || !Schema::hasColumn('push_notifications', 'service_id')) {
                $upgradeClass = new Upgrade26();
                $upgradeClass->run();
            }
            $this->showSuccessAlert(__("Fix Notification Error") . " " . __("Successfully"));
        } catch (\Exception $ex) {
            $this->showErrorAlert($ex->getMessage() ?? __("Failed"));
        }
    }

    public function fixAutoassignment()
    {
        $this->showCreateModal();
        // check if cron job is set
        $lastCronCall = setting('cronJobLastRunRaw', null);
        if ($lastCronCall == null) {
            $this->autoAssignmentChecks["cron_job"] = false;
        } else {
            $lastCronJobTimeFormatted = \Carbon\Carbon::parse($lastCronCall);
            $nowTime = \Carbon\Carbon::now();
            $mintuesDiff = $nowTime->diffInMinutes($lastCronJobTimeFormatted);
            if ($mintuesDiff > 5) {
                $this->autoAssignmentChecks["cron_job"] = false;
            } else {
                $this->autoAssignmentChecks["cron_job"] = true;
            }
        }

        //regular drivers online
        $regularDriversOnline = User::role('driver')->whereDoesntHave('vehicle')->where('is_online', 1)->count();
        $this->autoAssignmentChecks["online_regular_drivers"] = $regularDriversOnline > 0;
        //taxi drivers online
        $taxiDriversOnline = User::role('driver')->whereHas('vehicle')->where('is_online', 1)->count();
        $this->autoAssignmentChecks["online_taxi_drivers"] = $taxiDriversOnline > 0;

        //ready orders
        $readyOrders = Order::whereDoesntHave('taxi_order')->currentstatus('ready')->count();
        $this->autoAssignmentChecks["ready_orders"] = $readyOrders > 0;

        //taxi orders
        $taxiOrders = Order::whereHas('taxi_order')->currentstatus('pending')->count();
        $this->autoAssignmentChecks["pending_taxi_orders"] = $taxiOrders > 0;


        //drivers check
        // fetch drivers data from firestore
        try {
            $drivers = $this->getDrivers();
            $firestoreClient = $this->getFirebaseStoreClient();
            foreach ($drivers as $driverId) {
                //
                $driver = User::find($driverId);
                //delete driver node if driver doesn't exists on users databa
                if (empty($driver)) {
                    //
                    try {
                        $firestoreClient->deleteDocument("drivers/" . $driverId . "");
                    } catch (\Exception $error) {
                        logger("Driver delete error", [$error->getMessage() ?? '']);
                    }
                }
            }
            $this->autoAssignmentChecks["firebase_drivers"] = true;
        } catch (\Exception $error) {
            $this->autoAssignmentChecks["firebase_drivers"] = false;
            logger("drivers error", [$error->getMessage() ?? '']);
        }
    }


    public function fixReferralCodes()
    {

        try {
            $users = User::whereNull('code')->get();
            foreach ($users as $user) {
                $user->code = \Str::random(3) . "" . $user->id . "" . \Str::random(2);
                $user->save();
            }
            $this->showSuccessAlert(__("Referral code fixed") . " " . __("Successfully"));
        } catch (\Exception $ex) {
            $this->showErrorAlert($ex->getMessage() ?? __("Failed"));
        }
    }

    public function fixUserPermission()
    {

        try {
            Artisan::call('permission:cache-reset');
            Artisan::call('db:seed --class=PermissionsTableSeeder --force');
            Artisan::call('permission:cache-reset');
            $this->showSuccessAlert(__("User permissions fixed") . " " . __("Successfully"));
        } catch (\Exception $ex) {
            logger("error", [$ex]);
            $this->showErrorAlert($ex->getMessage() ?? __("Failed"));
        }
    }

    public function fixMissingUserRoles()
    {

        try {

            $users = User::doesntHave('roles')->get();
            foreach ($users as $user) {
                $user->syncRoles("client");
            }
            $this->showSuccessAlert((count($users) ?? 0) . " " . __("User missing role fixed") . " " . __("Successfully"));
        } catch (\Exception $ex) {
            logger("error", [$ex]);
            $this->showErrorAlert($ex->getMessage() ?? __("Failed"));
        }
    }




    public function getDrivers()
    {

        $firestoreClient = $this->getFirebaseStoreClient();
        $drivers = [];

        //
        $loadMore = true;
        $nextPageToken = "";
        // while ($loadMore) {
        //
        $driverDocuments = $firestoreClient->listDocuments('drivers', [
            "pageSize" => User::role('driver')->count(),
            'pageToken' => $nextPageToken
        ]);
        //
        if (array_key_exists('nextPageToken', $driverDocuments)) {
            $nextPageToken = $driverDocuments["nextPageToken"];
        }
        //
        if (!empty($driverDocuments['documents'])) {
            //
            foreach ($driverDocuments['documents'] as $key => $driverDocument) {

                //
                if ($driverDocument->has('id')) {
                    $drivers[] = $driverDocument->get('id');
                }
            }
            //
            $loadMore = true;
        } else {
            $loadMore = false;
        }
        // }

        return $drivers;
    }
}
