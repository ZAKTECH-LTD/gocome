<?php

namespace App\Observers;

use App\Models\Vehicle;
use App\Services\JobHandlerService;
use App\Traits\FirebaseAuthTrait;

class VehicleObserver
{

    use FirebaseAuthTrait;

    public function created(Vehicle $vehicle)
    {
        $driver = $vehicle->driver;
        //
        (new JobHandlerService())->driverVehicleTypeJob($driver);       
    }

    public function updated(Vehicle $vehicle)
    {
        $driver = $vehicle->driver;
        //
        (new JobHandlerService())->driverVehicleTypeJob($driver);

    }
}
