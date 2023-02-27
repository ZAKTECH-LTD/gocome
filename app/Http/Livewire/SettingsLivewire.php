<?php

namespace App\Http\Livewire;

use Exception;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\CommonMarkConverter;
use GeoSot\EnvEditor\Facades\EnvEditor;

class SettingsLivewire extends BaseLivewireComponent
{

    public $showNotification = false;
    public $showApp = false;
    public $showPrivacy = false;
    public $showContact = false;
    public $showTerms = false;
    public $showPageSetting = false;
    public $showCustomNotificationMessage = false;
    public $showFileLimits = false;

    protected $listeners = [
        'deleteModel',
        'goBack' => 'goBack',
    ];



    public function render()
    {
        return view('livewire.settings.index');
    }

    public function goBack(){
        $this->reset();
    }
}
