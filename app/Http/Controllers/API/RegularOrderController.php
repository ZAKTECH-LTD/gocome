<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAddress;
use App\Models\Vendor;
use App\Traits\GoogleMapApiTrait;
use Illuminate\Http\Request;


class RegularOrderController extends Controller
{
    use GoogleMapApiTrait;
    //
    public function summary(Request $request)
    {


        //delivery_address_id
        //vendor_id

        //
        $vendor = Vendor::find($request->vendor_id);

        //vendor has delivery_zones for delivery fee from zones
        if ($vendor != null && $vendor->delivery_zones->count() > 0) {

            //
            if ($request->delivery_address_id != "null" && !empty($request->delivery_address_id) && isset($request->delivery_address_id)) {
                $deliveryAddressLocation = $this->getDeliveryAddress($request->delivery_address_id);
                $destinationLatLngs = "" . $deliveryAddressLocation->latitude . "," . $deliveryAddressLocation->longitude;
            } else {
                $destinationLatLngs = $request->latlng;
            }

            //
            $deliveryZones = $vendor->delivery_zones;
            $distanceAmount = null;
            $latitude = explode(",", $destinationLatLngs)[0];
            $longitude = explode(",", $destinationLatLngs)[1];
            $cLatLng = [
                'lat' => $latitude,
                'lng' => $longitude
            ];
            //find the delivery zone that provided delivery address is within
            foreach ($deliveryZones as $deliveryZone) {
                $inBound = $this->insideBound($cLatLng, $deliveryZone->points);
                if ($inBound && $deliveryZone->delivery_fee != null) {
                    $distanceAmount = $deliveryZone->delivery_fee;
                    break;
                }
            }


            //
            if ($distanceAmount != null) {
                return response()->json([
                    "delivery_fee" => $distanceAmount,
                ]);
            }
        }

        //previous delivery fee calculation
        if (setting('enableGoogleDistance', 0)) {

            //
            if ($request->delivery_address_id != "null" && !empty($request->delivery_address_id) && isset($request->delivery_address_id)) {
                $deliveryAddressLocation = $this->getDeliveryAddress($request->delivery_address_id);
                $destinationLatLngs = "" . $deliveryAddressLocation->latitude . "," . $deliveryAddressLocation->longitude;
            } else {
                $destinationLatLngs = $request->latlng;
            }
            //

            $originLatLng = "" . $vendor->latitude . "," . $vendor->longitude;

            //
            try {
                $deliveryLocationDistance = $this->getTotalDistanceFromGoogle(
                    $originLatLng,
                    $destinationLatLngs
                );
            } catch (\Exception $ex) {
                $deliveryLocationDistance = $this->getLinearDistance(
                    $originLatLng,
                    $destinationLatLngs
                );
            }

            //


        } else {
            //linear distance calculation
            $deliveryLocationDistance = DeliveryAddress::distance($vendor->latitude, $vendor->longitude)
                ->where('id', $request->delivery_address_id)
                ->first()
                ->distance;
        }


        //calculate the distance price
        if ($vendor->charge_per_km) {
            $distanceAmount = $vendor->delivery_fee * $deliveryLocationDistance;
        } else {
            $distanceAmount = $vendor->delivery_fee;
        }
        //
        $distanceAmount += $vendor->base_delivery_fee;

        return response()->json([
            "delivery_fee" => $distanceAmount,
        ]);
    }



    //
    public function getDeliveryAddress($id): DeliveryAddress
    {
        return DeliveryAddress::find($id);
    }
}
