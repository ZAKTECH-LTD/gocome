<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\VendorType;
use App\Traits\GoogleMapApiTrait;

class VendorController extends Controller
{

    use GoogleMapApiTrait;

    public function index(Request $request)
    {

        //
        $latitude = $request->latitude;
        $longitude = $request->longitude;

        //the rest
        $vendors = $this->getVendorsQuery($request);

        //no location
        if (!empty($latitude)) {

            $vendors = $this->getVendorsQuery($request)->when($request->latitude, function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->within($request->latitude, $request->longitude);
                    })->orwhere(function ($query) use ($request) {
                        return $query->withinrange($request->latitude, $request->longitude);
                    });
                });
            })->when(!empty($request->type), function ($query) {
                $vendorsHomePageListCount = setting('vendorsHomePageListCount', $this->perPage);
                return $query->paginate($vendorsHomePageListCount);
            }, function ($query) {
                return $query->paginate($this->perPage);
            });

            if (!empty($vendors)) {

                $result = $vendors->items();
                $unsortedVendors = collect($result);
                $sortedVendors = $unsortedVendors->sortBy([
                    ['is_open', 'desc'],
                ]);

                $result = $vendors->setCollection($sortedVendors);
                $vendors = $result;
            }

            return $vendors;

            /*
            //OLD Code, to be removed in future update, when no issue is recored
            $rangeSearchVendors = $this->getVendorsQuery($request)
                ->whereDoesntHave('delivery_zones')
                ->havingRaw("delivery_range >= distance")
                ->when(!empty($request->type), function ($query) {
                    $vendorsHomePageListCount = setting('vendorsHomePageListCount', $this->perPage);
                    return $query->paginate($vendorsHomePageListCount);
                }, function ($query) {
                    return $query->paginate($this->perPage);
                });

            $ignoreIds = $rangeSearchVendors->pluck('id');


            $deliveryZoneSearchVendors = $this->getVendorsQuery($request)->when($latitude, function ($query) use ($latitude, $longitude) {
                $query->with('delivery_zones')->whereHas('delivery_zones', function ($query) use ($latitude, $longitude) {
                    $query->closeTo($latitude, $longitude);
                });
            })
                ->whereNotIn('id', $ignoreIds)
                ->when(!empty($request->type), function ($query) {
                    $vendorsHomePageListCount = setting('vendorsHomePageListCount', $this->perPage);
                    return $query->paginate($vendorsHomePageListCount);
                }, function ($query) {
                    return $query->paginate($this->perPage);
                });

            $result = $deliveryZoneSearchVendors;
            $result = $rangeSearchVendors->items();
            $result = array_merge($result, $deliveryZoneSearchVendors->items());

            $unsortedVendors = collect($result);
            $sortedVendors = $unsortedVendors->sortBy([
                ['is_open', 'desc'],
            ]);

            $result = $rangeSearchVendors->setCollection($sortedVendors);

            return $result;
            */
        } else {
            $vendorData = $vendors->when(!empty($request->type), function ($query) {
                $vendorsHomePageListCount = setting('vendorsHomePageListCount', $this->perPage);
                return $query->paginate($vendorsHomePageListCount);
            }, function ($query) {
                return $query->paginate($this->perPage);
            });

            $unsortedVendors = collect($vendorData->items());
            $sortedVendors = $unsortedVendors->sortBy([
                ['is_open', 'desc'],
            ]);
            $result = $vendorData->setCollection($sortedVendors);
            return $result;
        }
    }

    public function getVendorsQuery($request)
    {

        $oldVendorType = $request->type;
        $parcelVendorType = VendorType::where('slug', 'parcel')->first();
        $parcelVendorTypeId = $parcelVendorType != null ? $parcelVendorType->id : null;
        $vendorTypeId = $request->vendor_type_id;

        //the rest
        return Vendor::active()->inorder()->when($request->type == "top", function ($query) {
            return $query->withCount('sales')->orderBy('sales_count', 'DESC');
        })

            ->when($request->type == "featured", function ($query) {
                return $query->where('featured', 1);
            })
            ->when($request->type == "you", function ($query) {
                return $query->inRandomOrder();
            })
            ->when($request->type == "rated", function ($query) {
                return $query->orderByPowerJoinsAvg('ratings.rating', 'desc');
            })
            ->when($request->type == "fresh", function ($query) {
                return $query->latest();
            })
            ->when($oldVendorType == "package", function ($query) use ($parcelVendorTypeId) {
                return $query->where('vendor_type_id', $parcelVendorTypeId);
            })
            ->when($vendorTypeId, function ($query) use ($vendorTypeId) {
                return $query->where('vendor_type_id', $vendorTypeId);
            })
            ->when($request->package_type_id, function ($query) use ($request) {
                return $query->with(
                    [
                        'cities' => function ($query) {
                            $query->where('is_active', 1);
                        },
                        'states'  => function ($query) {
                            $query->where('is_active', 1);
                        },
                        'countries'  => function ($query) {
                            $query->where('is_active', 1);
                        },
                    ]
                )
                    ->withAndWhereHas('package_types_pricing', function ($query) use ($request) {
                        $query->where('package_type_id', $request->package_type_id);
                    });
            });
        // ->when($request->latitude, function ($query) use ($request) {
        //     return $query->distance($request->latitude, $request->longitude)
        //         ->orderBy('distance', 'asc');
        // });
    }


    public function show(Request $request, $id)
    {

        try {
            if (($request->type ?? "") == "small") {
                $vendor = Vendor::with(['menus' => function ($query) {
                    return $query->where('is_active', 1)->inorder();
                }, 'categories.sub_categories'])->findorfail($id);
            } else if (($request->type ?? "") == "brief") {
                $vendor = Vendor::findorfail($id);
            } else {
                $vendorId = $id;
                $vendor = Vendor::with(
                    [
                        'menus' => function ($query) use ($vendorId) {
                            return $query->inorder();
                        }, 'menus.products' => function ($query) use ($vendorId) {
                            return $query->withoutAppends()->where('is_active', 1)->where('vendor_id', $vendorId);
                        }, 'categories' => function ($query) use ($vendorId) {
                            return $query->inorder()->whereHas('products')->orWhereHas('sub_categories');
                        }, 'categories.sub_categories' => function ($query) use ($vendorId) {
                            return $query->inorder()->whereHas('products');
                        }, 'categories.sub_categories.products' => function ($query) use ($vendorId) {
                            return $query->withoutAppends()->where('is_active', 1)->where('vendor_id', $vendorId);
                        },
                    ],
                )->findorfail($id);
            }
            return $vendor;
        } catch (\Exception $ex) {

            return response()->json([
                "message" => $ex->getMessage() ?? __("No Vendor Found")
            ], 400);
        }
    }

    public function toggleVendorAvailablity(Request $request, $id)
    {


        if ((auth()->user()->vendor_id ?? null) != $id) {
            return response()->json([
                "message" => __("Unauthorised Access")
            ], 400);
        }

        try {

            $vendor = Vendor::findorfail($id);
            $vendor->is_open = !$vendor->is_open;
            $vendor->save();

            return response()->json([
                "vendor" => $vendor,
                "message" => __("Status Updated Successfully"),
            ], 200);
        } catch (\Exception $ex) {

            return response()->json([
                "message" => $ex->getMessage() ?? __("No Vendor Found")
            ], 400);
        }
    }

    public function fullDeatils(Request $request, $id)
    {

        if ((auth()->user()->vendor_id ?? null) != $id) {
            return response()->json([
                "message" => __("Unauthorised Access")
            ], 400);
        }

        try {
            $vendor = Vendor::with('earning', 'menus')->withCount('sales')->findorfail($id);
            $weeklyReport = $this->ordersChart($vendor);
            return response()->json([
                "vendor" => $vendor,
                "total_earnig" => (float) ($vendor->earning->amount ?? 0.00),
                "total_orders" => (int) $vendor->sales_count,
                "report" => $weeklyReport,
            ], 200);
        } catch (\Exception $ex) {

            return response()->json([
                "message" => $ex->getMessage() ?? __("No Vendor Found")
            ], 400);
        }
    }

    public function ordersChart($vendor)
    {

        $report = [];
        for ($loop = 0; $loop < 7; $loop++) {
            $date = Carbon::now()->startOfWeek()->addDays($loop);
            $formattedDate = $date->format("D");
            $data = Order::where('vendor_id', $vendor->id)->whereDate("created_at", $date)->count();

            array_push($report, ["date" => $formattedDate, "value" => $data]);
        }

        return $report;
    }
}
