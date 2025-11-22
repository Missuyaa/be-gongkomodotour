<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'trip_duration_id' => $this->trip_duration_id,
            'user_id' => $this->user_id,
            'hotel_occupancy_id' => $this->hotel_occupancy_id,
            'total_price' => $this->total_price,
            'total_pax' => $this->total_pax,
            'status' => $this->status,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_address' => $this->customer_address,
            'customer_country' => $this->customer_country,
            'customer_phone' => $this->customer_phone,
            'is_hotel_requested' => $this->is_hotel_requested,
            'start_date' => $this->start_date ? (is_string($this->start_date) ? $this->start_date : $this->start_date->format('Y-m-d')) : null,
            'end_date' => $this->end_date ? (is_string($this->end_date) ? $this->end_date : $this->end_date->format('Y-m-d')) : null,

            'trip' => $this->whenLoaded('trip', function () {
                return TripResource::make($this->trip);
            }),

            'trip_duration' => $this->whenLoaded('tripDuration', function () {
                return TripDurationResource::make($this->tripDuration);
            }),

            'boat' => $this->whenLoaded('boat', function () {
                return BoatResource::collection($this->boat);
            }),

            'cabin' => $this->whenLoaded('cabin', function () {
                return CabinResource::collection($this->cabin)->map(function ($cabin) {
                    return [
                        'id' => $cabin->id,
                        'boat_id' => $cabin->boat_id,
                        'cabin_name' => $cabin->cabin_name,
                        'bed_type' => $cabin->bed_type,
                        'min_pax' => $cabin->min_pax,
                        'max_pax' => $cabin->max_pax,
                        'base_price' => $cabin->base_price,
                        'additional_price' => $cabin->additional_price,
                        'status' => $cabin->status,
                        'created_at' => $cabin->created_at,
                        'updated_at' => $cabin->updated_at,
                        'booking_total_pax' => $cabin->pivot->total_pax,
                        'booking_total_price' => $cabin->pivot->total_price
                    ];
                });
            }),

            'user' => $this->whenLoaded('user', function () {
                return UserResource::make($this->user);
            }),

            'hotel_occupancy' => $this->whenLoaded('hotelOccupancy', function () {
                return HotelOccupancyResource::make($this->hotelOccupancy);
            }),

            'additional_fees' => $this->whenLoaded('additionalFees', function () {
                return AdditionalFeeResource::collection($this->additionalFees);
            }),

            // Surcharges diambil dari hotel occupancy (bukan dari trip)
            'surcharges' => $this->whenLoaded('hotelOccupancy', function () {
                return SurchargeResource::collection($this->hotelOccupancy->surcharges ?? collect());
            }),
        ];
    }
}
