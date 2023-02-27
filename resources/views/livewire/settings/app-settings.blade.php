@section('title', __('Mobile App Settings'))
<div>

    <x-baseview title="{{ __('Mobile App Settings') }}">

        <x-form action="saveAppSettings">

            <div class="">
                <div class='grid grid-cols-1 gap-4 md:grid-cols-2 '>
                    <x-input title="{{ __('App Name') }}" name="appName" />


                    {{-- country code --}}
                    <div>
                        <x-input title="{{ __('Country Code') }}" name="appCountryCode" />
                        <a href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2" target="_blank" class="mt-1 text-xs text-gray-500 underline">{{ __('List Of Country Codes') }}</a>
                        <p class="text-sm text-gray-500">
                            {{ __('Note: For example if you want to allow phone from Ghana you enter GH') }}
                        </p>
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __('Multiple Stops(Parcel Delivery)') }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableParcelMultipleStops" :defer="true" />
                    </div>
                    <x-input title="{{ __('Max Stops(Parcel Delivery)') }}" name="maxParcelStops" type="number" />
                    {{-- clear firebase --}}
                    <div class="block mt-4 text-sm">
                        <p>{{ __('Clear Firebase after order') }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="clearFirestore" :defer="true" />
                        <p class="text-xs text-gray-500">
                            {{ __('Note: This is to reduce the size of your firebase firestore, by removing completed or failed orders from the firebase firestore') }}
                        </p>
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __('Numeric Order Code') }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableNumericOrderCode" :defer="true" />
                    </div>
                </div>
                <div class='grid grid-cols-1 gap-4 p-4 mt-4 border rounded shadow md:grid-cols-2 '>
                    <x-input title="{{ __('Android App Download Link') }}" name="androidDownloadLink" />
                    <x-input title="{{ __('iOS App Download Link') }}" name="iosDownloadLink" />
                </div>

                {{-- Auth Layout --}}
                <p class="pt-4 mt-10 text-2xl border-t">Auth Related</p>
                <div class='grid grid-cols-1 gap-4 mb-10 md:grid-cols-2'>
                    {{-- enableOTPLogin --}}
                    <div class="block mt-4 text-sm">
                        <p>{{ __('OTP Login') }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableOTPLogin" :defer="true" />
                    </div>
                    {{-- Working --}}
                    <x-select :options="$smsGateways" :title="__('Phone OTP for verification')" name="otpGateway" />
                    <div></div>
                </div>
                <div class='grid grid-cols-1 gap-4 mb-10 md:grid-cols-3'>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Google Login") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="googleLogin" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Apple Login") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="appleLogin" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Facebook Login") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="facebbokLogin" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Auto Create Account with social login") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="auto_create_social_account" :defer="true" />
                    </div>

                    <div class="block mt-4 text-sm">
                        <p>{{ __("QR Code Login") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="qrcodeLogin" :defer="true" />
                    </div>
                </div>


                {{-- App Layout --}}
                <p class="pt-4 mt-10 text-2xl border-t">App Layout</p>
                <div class='grid grid-cols-1 gap-4 mb-10 md:grid-cols-2 lg:grid-cols-3'>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Calculate Distance via Google Map") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableGoogleDistance" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Single-Vendor Mode") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableSingleVendor" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Chat Option") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableChat" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Parcel Vendor By Location") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableParcelVendorByLocation" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Order Tracking") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableOrderTracking" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Allow Prescription") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableUploadPrescription" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Proof of delivery by delivery boy") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableProofOfDelivery" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Proof type") }}</p>
                        <x-select :options="['none','code','signature','photo']" title="" name="orderVerificationType" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __('Vendors Home Page List Count') }}</p>
                        <x-input title="" name="vendorsHomePageListCount" type="number" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __('Banner Height') }}</p>
                        <x-input title="" name="bannerHeight" type="number" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Allow vendors create drivers") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="allowVendorCreateDrivers" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Show only image on vendor types") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="showVendorTypeImageOnly" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Allow partners(Driver, Vendor) account registration") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="partnersCanRegister" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Fetch Data By Location") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableFatchByLocation" description="{{ __('This can be use to enforce only data within customer location is loaded') }}" :defer="true" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <p>{{ __("Allow multiple-vendor ordering from customer") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableMultipleVendorOrder" :defer="true" />
                    </div>
                </div>


                {{-- Driver releated settings --}}
                <p class="pt-4 mt-10 text-2xl border-t">{{ __('Driver App Settings') }}</p>
                <div class='grid grid-cols-1 gap-4 mb-10 md:grid-cols-2 lg:grid-cols-3'>

                    <div class="block mt-4 text-sm">
                        <p>{{ __("Allow taxi driver to switch to regular driver") }}</p>
                        <x-checkbox title="{{ __('Enable') }}" name="enableDriverTypeSwitch" :defer="true" />
                    </div>

                    <div class="block mt-4 text-sm">
                        <x-input title="{{ __('Accept Time Duration(seconds)') }}" name="alertDuration" type="number" />
                    </div>

                    <div class="block mt-4 text-sm">
                        <x-input title="{{ __('Driver order search radius') }}(KM)" name="driverSearchRadius" type="number" />
                    </div>

                    <div class="block mt-4 text-sm">
                        <x-input title="{{ __('Driver Max Acceptable Order') }}" name="maxDriverOrderAtOnce" type="number" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <x-input title="{{ __('Number of driver to be notified of new order') }}" name="maxDriverOrderNotificationAtOnce" type="number" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <x-input title="{{ __('Resend rejected auto-assignment notification(minutes)') }}" name="clearRejectedAutoAssignment" type="number" />
                    </div>

                    <div class="block mt-4 text-sm">
                        <x-input title="{{ __('Emergency Contact for drivers and customers') }}" name="emergencyContact" />
                    </div>

                    {{-- Location updating --}}
                    <div class="block mt-4 text-sm">
                        <x-input title="{{ __('Location Update Distance(Meter)') }}" name="distanceCoverLocationUpdate" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <x-input title="{{ __('Location Update Time(Seconds)') }}" name="timePassLocationUpdate" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <x-select title="{{ __('Auto-Assignment Status') }}" :options="$statuses ?? []" name="autoassignmentStatus" />
                    </div>
                    <div class="block mt-4 text-sm">
                        <x-select title="{{ __('Auto-Assignment System') }}" :options="$systemTypes ?? []" name="autoassignmentsystem" />
                    </div>
                </div>


                {{-- theme --}}
                <p class="pt-4 mt-4 text-2xl border-t">Theme</p>
                <p class="mt-4 text-lg border-b">Main Colors</p>
                <div class='grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3'>
                    <x-input title="Accent Color" name="accentColor" type="color" class="h-10" />
                    <x-input title="Primary Color" name="primaryColor" type="color" class="h-10" />
                    <x-input title="Primary Dark Color" name="primaryColorDark" type="color" class="h-10" />
                </div>
                {{-- other --}}
                <p class="mt-4 text-lg border-b">Onboarding Colors</p>
                <div class='grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3'>
                    <x-input title="Onboarding Page 1 Background Color" name="onboarding1Color" type="color" class="h-10" />
                    <x-input title="Onboarding Page 2 Background Color" name="onboarding2Color" type="color" class="h-10" />
                    <x-input title="Onboarding Page 3 Background Color" name="onboarding3Color" type="color" class="h-10" />
                    {{-- next --}}
                    <x-input title="Onboarding Indicator Dot Color" name="onboardingIndicatorDotColor" type="color" class="h-10" />
                    <x-input title="Onboarding Indicator Active Dot Color" name="onboardingIndicatorActiveDotColor" type="color" class="h-10" />
                </div>
                <p class="mt-4 text-lg border-b">Order Status Colors</p>
                <div class='grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3'>
                    <x-input title="Open Color" name="openColor" type="color" class="h-10" />
                    <x-input title="Close Color" name="closeColor" type="color" class="h-10" />
                    <x-input title="Delivery Color" name="deliveryColor" type="color" class="h-10" />
                    <x-input title="Pickup Color" name="pickupColor" type="color" class="h-10" />
                    <x-input title="Rating Color" name="ratingColor" type="color" class="h-10" />
                </div>
                <p class="mt-4 text-lg border-b">Order Status Colors</p>
                <div class='grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3'>
                    {{-- other status colors --}}
                    <x-input title="Pending Color" name="pendingColor" type="color" class="h-10" />
                    <x-input title="Preparing Color" name="preparingColor" type="color" class="h-10" />
                    <x-input title="Enroute Color" name="enrouteColor" type="color" class="h-10" />
                    <x-input title="Failed Color" name="failedColor" type="color" class="h-10" />
                    <x-input title="Cancelled Color" name="cancelledColor" type="color" class="h-10" />
                    <x-input title="Delivered Color" name="deliveredColor" type="color" class="h-10" />
                    <x-input title="Successful Color" name="successfulColor" type="color" class="h-10" />
                </div>
                <x-buttons.primary title="{{ __('Save Changes') }}" />
                <div>
        </x-form>

    </x-baseview>

</div>
