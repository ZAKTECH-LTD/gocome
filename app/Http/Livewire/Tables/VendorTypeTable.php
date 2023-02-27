<?php

namespace App\Http\Livewire\Tables;

use App\Models\VendorType;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Illuminate\Support\Facades\Auth;

class VendorTypeTable extends OrderingBaseDataTableComponent
{

    public $model = VendorType::class;
    public $checkDemo = true;
    public $header_view = 'components.buttons.new';
   

    public function query()
    {
        return VendorType::query();
    }

    public function columns(): array
    {

        $this->mount();
        return [
            Column::make(__('ID'),"id")->searchable()->sortable(),
            $this->logoColumn(),
            Column::make(__('Name'),'name')->searchable()->sortable(),
            Column::make(__('Color'),'color'),
            Column::make(__('Description'),'description')->searchable(),
            $this->activeColumn(),
            Column::make(__('Created At'), 'formatted_date'),
            $this->actionsColumn($actionView = 'components.buttons.simple_actions'),
            // Column::make(__('Actions'))->view('components.buttons.simple_actions'),
        ];
    }

    //
    public function deleteModel(){

        try{
            $this->showErrorAlert( "Delete Operation Not Allowed");
            return;
            $this->isDemo();
            \DB::beginTransaction();
            $this->selectedModel->delete();
            \DB::commit();
            $this->showSuccessAlert("Deleted");
        }catch(Exception $error){
            \DB::rollback();
            $this->showErrorAlert( $error->getMessage() ?? "Failed");
        }
    }

}
