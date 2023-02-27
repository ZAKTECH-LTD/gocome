<?php

namespace App\Http\Livewire\Tables\Reports;

use App\Exports\CommissionReportExport;
use App\Models\Commission;
use Maatwebsite\Excel\Facades\Excel;

use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;

class CommissionReportTable extends BaseReportTable
{

    public $model = Commission::class;

    public function query()
    {
        //
        return $this->model::with(
            'order'
        )
            ->when($this->getFilter('start_date'), function ($query, $sDate) {
                return $query->whereDate('created_at', ">=", $sDate);
            })->when($this->getFilter('end_date'), function ($query, $eDate) {
                return $query->whereDate('created_at', "<=", $eDate);
            });
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id'),
            Column::make(__('Order No'), 'order.code'),
            Column::make(__('Vendor'), 'order.vendor.name')->searchable(),
            Column::make(__('Admin Vendor Commission'), 'vendor_commission')->format(function ($value, $column, $row) {
                return view('components.table.price', $data = [
                    "model" => $row,
                    "column" => $column,
                    "value" => $value,
                ]);
            })->sortable(),
            Column::make(__('Driver'), 'order.driver.name')->searchable(),
            Column::make(__('Admin Driver Commission'), 'driver_commission')->format(function ($value, $column, $row) {
                return view('components.table.price', $data = [
                    "model" => $row,
                    "column" => $column,
                    "value" => $value,
                ]);
            })->sortable(),
            Column::make(__('Created At'), 'formatted_date'),
        ];
    }

    public function filters(): array
    {
        return [
            'start_date' => Filter::make(__('Start Date'))
                ->date([
                    'min' => now()->subYear()->format('Y-m-d'), // Optional
                    'max' => now()->format('Y-m-d') // Optional
                ]),
            'end_date' => Filter::make(__('End Date'))
                ->date([
                    'min' => now()->subYear()->format('Y-m-d'), // Optional
                    'max' => now()->format('Y-m-d') // Optional
                ])
        ];
    }


    public function exportSelected()
    {
        $this->isDemo(true);
        //
        if ($this->selectedRowsQuery->count() > 0) {
            $fileName = "commission_report(";
            //
            if ($this->getFilter('start_date')) {
                $fileName .= $this->getFilter('start_date') . " - ";
            }
            //
            else if ($this->getFilter('end_date')) {
                $fileName .= $this->getFilter('end_date');
            }

            $fileName .= ").xlsx";

            //
            $dataSet = $this->selectedRowsQuery->get()->toArray();
            //
            return Excel::download(new CommissionReportExport($dataSet), $fileName);
        } else {
            //
        }
    }
}
