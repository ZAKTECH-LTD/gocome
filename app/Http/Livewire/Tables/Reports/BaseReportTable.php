<?php

namespace App\Http\Livewire\Tables\Reports;

use App\Http\Livewire\Tables\BaseDataTableComponent;

class BaseReportTable extends BaseDataTableComponent
{

    public array $filters = [];
    public array $bulkActions = [];
    public function mount()
    {
        $this->filters = [
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        $this->bulkActions = [
            'exportSelected' => __('Export'),
        ];
    }

   
}
