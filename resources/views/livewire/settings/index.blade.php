@section('title', __('Settings'))
<div>

    <x-baseview title="{{__('Settings')}}">


        {{-- sub components  --}}
        @if ($this->showNotification)
        <livewire:settings.notification />
        @elseif($this->showApp)
        <livewire:settings.web-app-settings />
        @elseif ($this->showPrivacy)
        <livewire:settings.privacy-policy />
        @elseif ($this->showContact)
        <livewire:settings.contact />
        @elseif ($this->showTerms)
        <livewire:settings.terms />
        @elseif ($this->showPageSetting)
        <livewire:settings.page />
        @elseif ($this->showCustomNotificationMessage)
        <livewire:settings.custom-notification-message />
        @elseif ($this->showFileLimits)
        <livewire:settings.file-limit />
        @else
        @include('livewire.settings.list')
        @endif
    </x-baseview>

</div>
