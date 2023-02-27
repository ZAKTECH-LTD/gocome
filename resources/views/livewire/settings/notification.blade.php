<div>

    <x-baseview title="Push notification setting(Firebase)">

        <x-form action="saveNotificationSetting" backPressed="$emitUp('goBack')">
            <div class="flex w-full space-x-10">
                <div class="w-full md:w-4/5 lg:w-5/12">
                    <x-input title="API Key" name="apiKey" />
                    <x-input title="Project ID" name="projectId" />
                    <x-input title="Message Sender ID" name="messagingSenderId" />
                    <x-input title="App ID" name="appId" />
                    <x-input title="Web Push Key Pair" name="vapidKey" />
                    <x-media-upload title="Service Account" name="photo" :photo="$photo" :photoInfo="$photoInfo" :image="false" types="JSON" rules="application/JSON" />

                </div>
                <div class="w-full md:w-4/5 lg:w-5/12">
                    <div class="block pb-3 mt-4 text-sm border-b">
                        <p>{{ __('Notify Admin for order updates') }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="notifyAdmin" :defer="true" />
                    </div>
                    <div class="block pb-3 mt-4 text-sm border-b">
                        <p>{{ __('Notify CityAdmin for order updates') }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="notifyCityAdmin" :defer="true" />
                    </div>

                    <div class="block pb-3 mt-4 text-sm border-b">
                        <p>{{ __('Communicate via Jobs') }}</p>
                        <x-checkbox title="{{ __('Enable') }}" description="{{ __('This will improve mobile load like on page like placing order, assigning order, updating order etc.') }}" name="useFCMJob" :defer="true" />
                        {{--  delay seconds  --}}
                        <x-input title="{{ __('Delay Time(seconds)') }}" name="delayFCMJobSeconds" />
                    </div>

                </div>
            </div>
            <div class="w-full md:w-4/5 lg:w-5/12">
                <x-buttons.primary title="{{ __('Save Changes') }}" />
            </div>
        </x-form>

    </x-baseview>

</div>
