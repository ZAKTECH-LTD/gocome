<?php

namespace App\Http\Livewire;

use Exception;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\CommonMarkConverter;
use GeoSot\EnvEditor\Facades\EnvEditor;

class TaxiSettingLivewire extends BaseLivewireComponent
{

    // App settings
    public $cancelPendingTaxiOrderTime;
    public $drivingSpeed;
    public $taxiMaxScheduleDays;
    public $pending;
    public $preparing;
    public $ready;
    public $enroute;
    public $completed;
    public $cancelled;
    public $failed;
    public $multipleCurrency;
    public $canScheduleTaxiOrder;
    public $taxiUseFirebaseServer;
    public $delayTaxiMatching;
    public $delayResearchTaxiMatching;
    public $requestBookingCode;
    public $bookingCodeOptions = ["none", "before", "after"];
    public $recalculateFare;


    public function mount()
    {
        $this->cancelPendingTaxiOrderTime = setting('taxi.cancelPendingTaxiOrderTime', 2);
        $this->drivingSpeed = setting('taxi.drivingSpeed', 50);
        $this->taxiMaxScheduleDays = setting('taxi.taxiMaxScheduleDays', 3);
        $this->pending = setting('taxi.msg.pending', "");
        $this->preparing = setting('taxi.msg.preparing', "");
        $this->ready = setting('taxi.msg.ready', "");
        $this->enroute = setting('taxi.msg.enroute', "");
        $this->completed = setting('taxi.msg.completed', "");
        $this->cancelled = setting('taxi.msg.cancelled', "");
        $this->failed = setting('taxi.msg.failed', "");
        $this->multipleCurrency = (bool) setting('taxi.multipleCurrency', false);
        $this->canScheduleTaxiOrder = (bool) setting('taxi.canScheduleTaxiOrder', false);
        $this->taxiUseFirebaseServer = (bool) setting('taxiUseFirebaseServer');
        $this->delayTaxiMatching = setting('taxiDelayTaxiMatching', 2);
        $this->delayResearchTaxiMatching = setting('delayResearchTaxiMatching', 30);
        $this->requestBookingCode = setting('taxi.requestBookingCode');
        $this->recalculateFare = (bool) setting('taxi.recalculateFare', false);
    }

    public function render()
    {
        return view('livewire.taxi.taxi_settings');
    }




    public function saveSettings()
    {


        try {

            $this->isDemo();

            $appSettings = [
                'taxi.cancelPendingTaxiOrderTime' =>  $this->cancelPendingTaxiOrderTime,
                'taxi.drivingSpeed' =>  $this->drivingSpeed,
                'taxi.msg.pending' =>  $this->pending,
                'taxi.msg.preparing' =>  $this->preparing,
                'taxi.msg.ready' =>  $this->ready,
                'taxi.msg.enroute' =>  $this->enroute,
                'taxi.msg.completed' =>  $this->completed,
                'taxi.msg.cancelled' =>  $this->cancelled,
                'taxi.msg.failed' =>  $this->failed,
                'taxi.recalculateFare' =>  $this->recalculateFare,
                'taxi.multipleCurrency' =>  $this->multipleCurrency,
                'taxi.canScheduleTaxiOrder' =>  $this->canScheduleTaxiOrder,
                'taxi.taxiMaxScheduleDays' =>  $this->taxiMaxScheduleDays,
                'taxi.requestBookingCode' =>  $this->requestBookingCode,
                'taxiUseFirebaseServer' =>  $this->taxiUseFirebaseServer,
                'taxiDelayTaxiMatching' =>  $this->delayTaxiMatching,
                'delayResearchTaxiMatching' =>  $this->delayResearchTaxiMatching,
            ];

            // update the site name
            setting($appSettings)->save();



            $this->showSuccessAlert(__("App Settings saved successfully!"));
        } catch (Exception $error) {
            $this->showErrorAlert($error->getMessage() ?? __("App Settings save failed!"));
        }
    }
}
