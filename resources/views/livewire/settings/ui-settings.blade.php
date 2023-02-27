@section('title', __('UI Settings'))
<div>

    <x-baseview title="{{ __('UI Settings') }}">

        <x-form action="save">

            <div class="">
                <x-details.item title="{{ __('Home Screen') }}">
                    <div class='grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4'>
                        <x-checkbox title="{{ __('Banner') }}" description="{{ __('Show Banner On Home Screen') }}" name="showBannerOnHomeScreen" />
                        <x-select title="{{ __('Banner Position') }}" :options="['Top','Bottom']" name="bannerPosition" />
                        <x-select title="{{ __('Vendor Type Listing Style') }}" :options="['Both','GridView','ListView']" name="vendortypeListStyle" />
                        <x-input title="{{ __('Vendor Type Per Row') }}" name="vendortypePerRow" type="number" />
                        <x-checkbox title="{{ __('Wallet') }}" description="{{ __('Show Wallet On Home Screen') }}" name="showWalletOnHomeScreen" />
                        <x-select title="{{ __('Home Screen Style') }}" :options="$homeViewStyles" name="homeViewStyle" />
                    </div>
                </x-details.item>
                <hr class="my-4" />
                <x-details.item title="{{ __('Category') }}">
                    <div class='grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4'>

                        <x-input title="{{ __('Width') }}" name="categorySize_w" type="number" />
                        <x-input title="{{ __('Height') }}" name="categorySize_h" type="number" />
                        <x-input title="{{ __('Category Text Size') }}" name="categorySize_text_size" type="number" />
                        <x-input title="{{ __('Category Per Row') }}" name="categoryPerRow" type="number" />
                        <x-input title="{{ __('Category Per Page') }}" name="categoryPerPage" type="number" />
                    </div>
                </x-details.item>
                <hr class="my-4" />
                <x-details.item title="{{ __('Currency') }}">
                    <div class='grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4'>

                        <x-select title="{{ __('Location') }}" name="currencyLocation" :options="['Left','Right']" />
                        <x-input title="{{ __('Thousand separator') }}" name="currencyFormat" />
                        <x-input title="{{ __('Decimal separator') }}" name="currencyDecimalFormat" />
                        <x-input title="{{ __('Decimals') }}" name="currencyDecimals" type="number" />
                    </div>
                </x-details.item>
                <hr class="my-4" />
                <x-details.item title="{{ __('Vendor') }}">
                    <div class='grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4'>
                        <x-checkbox 
                            title="{{ __('Enable') }}" 
                            description="{{ __('Show vendor phone details to customer') }}" 
                            name="showVendorPhone" :defer="true" />
                    </div>
                </x-details.item>
                <hr class="my-4" />
                <x-details.item title="{{ __('Chat') }}">
                    <div class='grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4'>
                        <x-checkbox title="{{ __('Enable') }}" description="{{ __('Allow chat between vendor and customer/driver') }}" name="canVendorChat" :defer="true" />
                        <x-checkbox title="{{ __('Enable') }}" description="{{ __('Allow chat between customer and vendor/driver') }}" name="canCustomerChat" :defer="true" />
                        <x-checkbox title="{{ __('Enable') }}" description="{{ __('Allow chat between driver and customer/vendor') }}" name="canDriverChat" :defer="true" />
                    </div>
                </x-details.item>
                <hr class="my-4" />

                <div class='grid grid-cols-1 gap-4 md:grid-cols-2 '>
                    <x-buttons.primary title="{{ __('Save Changes') }}" />
                </div>
        </x-form>

    </x-baseview>

</div>
