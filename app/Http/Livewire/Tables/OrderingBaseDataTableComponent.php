<?php

namespace App\Http\Livewire\Tables;


class OrderingBaseDataTableComponent extends BaseDataTableComponent
{

    public string $defaultSortColumn = 'in_order';
    public string $defaultSortDirection = 'asc';
    public bool $reorderEnabled = true;

    // public function mount(){
    //     $itemCount = $this->query()->count();
    //     if($itemCount > 30){
    //         $this->reorderEnabled = false;
    //     }else{
    //         $this->reorderEnabled = true;
    //     }
    // }

    // Reorder called
    public function reorder($items)
    {
        //
        try {
            $this->isDemo();
            if ($this->model != null) {
                foreach ($items as $item) {
                    $modelData = $this->model::find($item['value']);
                    $modelData->in_order = $item['order'] ?? 1;
                    $modelData->save();
                }
            }
        } catch (\Exception $error) {
            $this->showWarningAlert($error->getMessage() ?? "Failed");
        }
    }
}
