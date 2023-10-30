<?php

namespace App\Http\Livewire\Tables;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;

class ProductTable extends OrderingBaseDataTableComponent
{

    public $model = Product::class;
    public bool $columnSelect = false;

    public function filters(): array
    {
        return [
            'digital' => Filter::make(__("Digital"))
                ->select([
                    '' => __('Any'),
                    '1' => __('Yes'),
                    '0' => __('No'),
                ]),
        ];
    }


    public function query()
    {

        $user = User::find(Auth::id());
        if ($user->hasRole('admin')) {
            $mQuery = Product::when($this->getFilter('digital'), fn ($query, $isDigital) => $query->whereDigital($isDigital));
        } elseif ($user->hasRole('city-admin')) {
            $mQuery = Product::with('vendor')->whereHas("vendor", function ($query) {
                return $query->where('creator_id', Auth::id());
            });
        } else {
            $mQuery = Product::where("vendor_id", Auth::user()->vendor_id);
        }

        return $mQuery->withCount('options')
            ->when($this->getFilter('digital'), function ($query, $isDigital) {
                return $query->whereDigital($isDigital);
            });
    }

    public function setTableRowClass($row): ?string
    {
        return $row->is_active ? null : 'inactive-item';
    }


    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')->searchable()->sortable(),
            $this->xsImageColumn(),
            Column::make(__('Name'), 'name')->addClass('w-3/12 line-clamp-1 text-ellipsis truncate')->searchable()->sortable(),
            Column::make(__('Price'), 'price')->format(function ($value, $column, $row) {
                if ($row->discount_price) {
                    $text = "<span class='font-medium'>" . currencyFormat($row->discount_price ??  '') . "</span>";
                    $text .= " <span class='text-xs line-through'>" . currencyFormat($row->price) . "</span>";
                } else {
                    $text = currencyFormat($value ??  '');
                }
                return view('components.table.plain', $data = [
                    "text" => $text
                ]);
            })->searchable()->sortable(),
            Column::make(__('Available Qty'), "available_qty")->sortable(),
            Column::make(__('Has Options'), 'has_options')->format(function ($value, $column, $row) {
                if ($value) {
                    return view('components.table.check');
                } else {
                    return view('components.table.close');
                }
            })->sortable(function ($query, $direction) {
                return $query->orderBy('options_count', $direction);
            }),
            Column::make(__('Actions'))->addClass('flex items-center')->format(function ($value, $column, $row) {
                return view('components.buttons.product_actions', $data = [
                    "model" => $row
                ]);
            }),


        ];
    }


    //
    public function deleteModel()
    {

        try {
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
}
