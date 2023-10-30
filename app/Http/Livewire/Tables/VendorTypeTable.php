<?php

namespace App\Http\Livewire\Tables;

use App\Models\VendorType;
use Rappasoft\LaravelLivewireTables\Views\Column;

class VendorTypeTable extends OrderingBaseDataTableComponent
{

    public $model = VendorType::class;
    public string $defaultSortColumn = 'is_active';
    public string $defaultSortDirection = 'desc';


    public function query()
    {
        return VendorType::query();
    }

    public function columns(): array
    {

        return [
            Column::make(__('ID'), "id")->searchable()->sortable(),
            $this->logoColumn(),
            Column::make(__('Name'), 'name')->searchable()->sortable(),
            Column::make(__('Slug'), 'slug')->searchable()->sortable(),
            Column::make(__('Color'), 'color'),
            Column::make(__('Description'), 'description')->searchable(),
            $this->activeColumn(),
            Column::make(__('Created At'), 'formatted_date'),
            $this->actionsColumn($actionView = 'components.buttons.simple_actions'),
        ];
    }

    //
    public function deleteModel()
    {

        try {
            $this->showErrorAlert("Delete Operation Not Allowed");
            return;
            $this->isDemo();
            \DB::beginTransaction();
            $this->selectedModel->delete();
            \DB::commit();
            $this->showSuccessAlert("Deleted");
        } catch (Exception $error) {
            \DB::rollback();
            $this->showErrorAlert($error->getMessage() ?? "Failed");
        }
    }

    public function activateModel()
    {

        try {
            $this->isDemo();
            $this->selectedModel->is_active = true;
            $this->selectedModel->save();
            $this->showSuccessAlert(__("Activated"));
        } catch (\Exception $error) {
            $this->showErrorAlert($error->getMessage() ?? __("Failed"));
        }
    }


    public function deactivateModel()
    {

        try {
            $this->isDemo();
            $this->selectedModel->is_active = false;
            $this->selectedModel->save();
            $this->showSuccessAlert(__("Deactivated"));
        } catch (\Exception $error) {
            $this->showErrorAlert($error->getMessage() ?? __("Failed"));
        }
    }
}