<?php

namespace App\Http\Livewire;

use Exception;

class UISettingsLivewire extends BaseLivewireComponent
{

    // App settings
    public $categorySize_w;
    public $categorySize_h;
    public $categorySize_text_size;
    public $categoryPerRow;
    public $categoryPerPage;
    // Currency
    public $currencyLocation;
    public $currencyFormat;
    public $currencyDecimals;
    public $currencyDecimalFormat;
    //home
    public $showBannerOnHomeScreen;
    public $showWalletOnHomeScreen;
    public $vendortypePerRow;
    public $bannerPosition;
    public $vendortypeListStyle;
    public $homeViewStyle;
    public $homeViewStyles = [
        [
            "id" => 1,
            "name" => "Original",
        ],
        [
            "id" => 2,
            "name" => "Modern",
        ],
        [
            "id" => 3,
            "name" => "Plain",
        ]
    ];

    public $showVendorPhone;
    public $canVendorChat;
    public $canCustomerChat;
    public $canDriverChat;

    public $rules = [
        "categorySize_w" => "required|numeric",
        "categorySize_h" => "required|numeric",
        "categorySize_text_size" => "required|numeric",
        "categoryPerRow" => "required|numeric",
        "categoryPerPage" => "required|numeric",
        "currencyLocation" => "required",
        "currencyFormat" => "required",
        "currencyDecimals" => "required",
        "currencyDecimalFormat" => "required",
    ];


    public function prepareData()
    {
        $this->categorySize_w = setting('ui.categorySize.w', 40);
        $this->categorySize_h = setting('ui.categorySize.h', 40);
        $this->categorySize_text_size = setting('ui.categorySize.text.size', 12);
        $this->categoryPerRow = setting('ui.categorySize.row', 4);
        $this->categoryPerPage = setting('ui.categorySize.page', 8);
        //
        $this->currencyLocation = setting('ui.currency.location', 8);
        $this->currencyFormat = setting('ui.currency.format', ",");
        $this->currencyDecimalFormat = setting('ui.currency.decimal_format', ".");
        $this->currencyDecimals = setting('ui.currency.decimals', 2);

        //
        $this->showBannerOnHomeScreen = (bool) setting('ui.home.showBannerOnHomeScreen', false);
        $this->showWalletOnHomeScreen = (bool) setting('ui.home.showWalletOnHomeScreen', true);
        $this->vendortypePerRow = setting('ui.home.vendortypePerRow', 2);
        $this->bannerPosition = setting('ui.home.bannerPosition', 'top');
        $this->vendortypeListStyle = setting('ui.home.vendortypeListStyle', 'both');
        $this->homeViewStyle = setting('ui.home.homeViewStyle', '1');

        //
        $this->showVendorPhone = (bool) setting('ui.showVendorPhone', true);
        $this->canVendorChat = (bool) setting('ui.chat.canVendorChat', true);
        $this->canCustomerChat = (bool) setting('ui.chat.canCustomerChat', true);
        $this->canDriverChat = (bool) setting('ui.chat.canDriverChat', true);
    }

    public function render()
    {
        $this->prepareData();
        return view('livewire.settings.ui-settings');
    }


    public function save()
    {

        $this->validate();

        try {

            $this->isDemo();
            $appSettings = [
                'ui' => [
                    "categorySize" => [
                        "w" => $this->categorySize_w,
                        "h" => $this->categorySize_h,
                        "row" => $this->categoryPerRow,
                        "page" => $this->categoryPerPage,
                        "text" => [
                            "size" => $this->categorySize_text_size,
                        ],
                    ],
                    "currency" => [
                        "location" => $this->currencyLocation,
                        "format" => $this->currencyFormat,
                        "decimal_format" => $this->currencyDecimalFormat,
                        "decimals" => $this->currencyDecimals,
                    ],

                    "home" => [
                        "showWalletOnHomeScreen" => $this->showWalletOnHomeScreen,
                        "showBannerOnHomeScreen" => $this->showBannerOnHomeScreen,
                        "vendortypePerRow" => $this->vendortypePerRow,
                        "bannerPosition" => $this->bannerPosition,
                        "vendortypeListStyle" => $this->vendortypeListStyle,
                        "homeViewStyle" => $this->homeViewStyle,
                    ],
                    "chat" => [
                        "canVendorChat" => $this->canVendorChat,
                        "canCustomerChat" => $this->canCustomerChat,
                        "canDriverChat" => $this->canDriverChat,
                    ],

                    "showVendorPhone" => $this->showVendorPhone,
                ],
            ];

            // update the site name
            setting($appSettings)->save();



            $this->showSuccessAlert(__("UI Settings saved successfully!"));
            $this->reset();
        } catch (Exception $error) {
            $this->showErrorAlert($error->getMessage() ?? __("UI Settings save failed!"));
        }
    }
}
