<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Models\Cabin;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Contracts\AdditionalFeeRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class BookingRepository implements BookingRepositoryInterface
{
    /**
     * @var Booking
     */
    protected $booking;

    /**
     * @var AdditionalFeeRepositoryInterface
     */
    protected $additionalFeeRepository;

    /**
     * Konstruktor BookingRepository.
     *
     * @param Booking $booking
     * @param AdditionalFeeRepositoryInterface $additionalFeeRepository
     */
    public function __construct(Booking $booking, AdditionalFeeRepositoryInterface $additionalFeeRepository)
    {
        $this->booking = $booking;
        $this->additionalFeeRepository = $additionalFeeRepository;
    }

    /**
     * Mengambil semua bookings.
     *
     * @return mixed
     */
    public function getAllBookings()
    {
        return $this->booking->with([
            'trip',
            'trip.assets',
            'tripDuration',
            'tripDuration.tripPrices',
            'boat',
            'boat.assets',
            'cabin',
            'cabin.assets',
            'user',
            'hotelOccupancy',
            'hotelOccupancy.surcharges',
            'additionalFees'
        ])->get();
    }

    /**
     * Mengambil booking berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getBookingById($id)
    {
        try {
            return $this->booking->with([
                'trip',
                'trip.assets',
                'tripDuration',
                'tripDuration.tripPrices',
                'boat',
                'boat.assets',
                'cabin',
                'cabin.assets',
                'user',
                'hotelOccupancy',
                'hotelOccupancy.surcharges',
                'additionalFees'
            ])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Booking with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Mengambil booking berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getBookingByName($name)
    {
        return $this->booking->where('name', $name)
            ->with([
                'trip',
                'trip.assets',
                'tripDuration',
                'tripDuration.tripPrices',
                'boat',
                'boat.assets',
                'cabin',
                'cabin.assets',
                'user',
                'hotelOccupancy',
                'additionalFees'
            ])->first();
    }

    /**
     * Mengambil booking berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getBookingByStatus($status)
    {
        return $this->booking->with([
            'trip',
            'trip.assets',
            'tripDuration',
            'tripDuration.tripPrices',
            'boat',
            'boat.assets',
            'cabin',
            'cabin.assets',
            'user',
            'hotelOccupancy',
            'additionalFees'
        ])
            ->where('status', $status)
            ->get();
    }

    /**
     * Membuat booking baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createBooking(array $data)
    {
        try {
            // Buat booking utama
            $booking = $this->booking->create($data);

            // Sinkronisasi relasi cabin
            if (isset($data['cabins']) && is_array($data['cabins'])) {
                $cabinPivotData = [];
                foreach ($data['cabins'] as $cabinData) {
                    $cabin = Cabin::find($cabinData['cabin_id']);
                    if ($cabin) {
                        $cabinPivotData[$cabin->id] = [
                            'total_pax' => $cabinData['total_pax'],
                            'total_price' => $cabinData['total_price']
                        ];
                    }
                }
                $booking->cabin()->sync($cabinPivotData);
            }

            // Sinkronisasi relasi boat
            if (isset($data['boat_ids']) && is_array($data['boat_ids'])) {
                $booking->boat()->sync($data['boat_ids']);
            }

            // Sinkronisasi additional fees
            $this->syncAdditionalFees($booking, $data);

            return $booking;
        } catch (\Exception $e) {
            Log::error("Failed to create booking: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Memperbarui booking berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateBooking($id, array $data)
    {
        try {
            $booking = $this->findBooking($id);
            if (!$booking) {
                return null;
            }

            $booking->update($data);

            // Sinkronisasi relasi cabin jika ada
            if (isset($data['cabins']) && is_array($data['cabins'])) {
                $cabinPivotData = [];
                foreach ($data['cabins'] as $cabinData) {
                    $cabin = Cabin::find($cabinData['cabin_id']);
                    if ($cabin) {
                        $cabinPivotData[$cabin->id] = [
                            'total_pax' => $cabinData['total_pax'],
                            'total_price' => $cabinData['total_price']
                        ];
                    }
                }
                $booking->cabin()->sync($cabinPivotData);
            }

            // Sinkronisasi relasi boat
            if (isset($data['boat_ids']) && is_array($data['boat_ids'])) {
                $booking->boat()->sync($data['boat_ids']);
            }

            // Sinkronisasi additional fees
            $this->syncAdditionalFees($booking, $data);

            return $booking;
        } catch (\Exception $e) {
            Log::error("Failed to update booking: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Menghapus booking berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteBooking($id)
    {
        $booking = $this->findBooking($id);

        if ($booking) {
            try {
                $booking->delete();
                return true;
            } catch (\Exception $e) {
                Log::error("Failed to delete booking with ID {$id}: {$e->getMessage()}");
                return false;
            }
        }
        return false;
    }

    /**
     * Helper method untuk menemukan booking berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    protected function findBooking($id)
    {
        try {
            return $this->booking->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Booking with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Method untuk menambahkan atau memperbarui additional fees.
     *
     * @param Booking $booking
     * @param array $data
     * @return void
     */
    protected function syncAdditionalFees($booking, $data)
    {
        $syncFees = [];

        // Tambahkan fee wajib berdasar trip_id booking
        if ($booking->trip_id) {
            $requiredFees = $this->additionalFeeRepository->getAdditionalFeesByTripId($booking->trip_id);

            if ($requiredFees) {
                foreach ($requiredFees as $fee) {
                    if ($fee->is_required && $fee->status === 'Aktif') {
                        $syncFees[$fee->id] = ['total_price' => $fee->price];
                    }
                }
            }
        }

        // Proses optional fee dari input
        if (isset($data['additional_fee_ids']) && is_array($data['additional_fee_ids'])) {
            foreach ($data['additional_fee_ids'] as $fee) {
                if (is_array($fee)) {
                    $feeObj = $this->additionalFeeRepository->getAdditionalFeeById($fee['additional_fee_id']);
                    if ($feeObj && !$feeObj->is_required && $feeObj->status === 'Aktif') {
                        $syncFees[$feeObj->id] = ['total_price' => $fee['total_price'] ?? $feeObj->price];
                    }
                } else {
                    $feeObj = $this->additionalFeeRepository->getAdditionalFeeById($fee);
                    if ($feeObj && !$feeObj->is_required && $feeObj->status === 'Aktif') {
                        $syncFees[$feeObj->id] = ['total_price' => $feeObj->price];
                    }
                }
            }
        }

        if (!empty($syncFees)) {
            $booking->additionalFees()->sync($syncFees);
        }
    }

    private function calculateBookingPrices(Booking $booking)
    {
        // Load relasi yang dibutuhkan
        $booking->load(['cabin', 'hotelOccupancy', 'hotelOccupancy.surcharges', 'tripDuration', 'additionalFees']);

        // Hitung harga cabin (ini akan memicu log perhitungan cabin)
        $cabinPrice = $booking->computed_cabin_price;

        // Hitung harga hotel
        $hotelPrice = 0;
        if ($booking->hotelOccupancy && $booking->tripDuration) {
            $nights = $booking->tripDuration->duration_nights ?? ($booking->tripDuration->duration_days - 1);
            $hotelPrice = $booking->hotelOccupancy->calculateHotelFee($booking->total_pax, $nights);
        }

        // Hitung harga trip
        $tripPrice = 0;
        if ($booking->tripDuration) {
            // Cek trip prices yang sesuai dengan trip duration ini
            $tripPrices = $booking->tripDuration->tripPrices()
                ->where('status', 'Aktif')
                ->where('pax_min', '<=', $booking->total_pax)
                ->where('pax_max', '>=', $booking->total_pax)
                ->first();

            if ($tripPrices) {
                $tripPrice = $tripPrices->price_per_pax;
            }

            // Log untuk debugging
            Log::info('Trip Price Calculation', [
                'trip_duration_id' => $booking->trip_duration_id,
                'total_pax' => $booking->total_pax,
                'found_price' => $tripPrice,
                'trip_prices' => $tripPrices
            ]);
        }

        // Hitung surcharge jika ada (menggunakan surcharges dari hotel occupancy)
        $surchargePrice = 0;
        if ($booking->hotelOccupancy && $booking->hotelOccupancy->relationLoaded('surcharges')) {
            $currentDate = now();
            $activeSurcharge = $booking->hotelOccupancy->surcharges->first(function ($surcharge) use ($currentDate) {
                return $surcharge->status === 'Aktif' &&
                    $currentDate->between($surcharge->start_date, $surcharge->end_date);
            });

            if ($activeSurcharge) {
                $surchargePrice = (float) $activeSurcharge->surcharge_price;
                Log::info('Surcharge Calculation', [
                    'surcharge_id' => $activeSurcharge->id,
                    'season' => $activeSurcharge->season,
                    'surcharge_price' => $surchargePrice
                ]);
            }
        }

        // Hitung total dasar
        $baseTotal = ($cabinPrice + $hotelPrice + $tripPrice + $surchargePrice) * $booking->total_pax;

        // Hitung booking fee
        $bookingFeeTotal = $booking->additionalFees->sum(function ($fee) {
            return $fee->pivot->total_price;
        });

        $finalTotalPrice = $baseTotal + $bookingFeeTotal;

        // Log hasil perhitungan
        Log::info('Hasil perhitungan total price booking', [
            'total_pax' => $booking->total_pax,
            'cabin_price' => $cabinPrice,
            'hotel_price' => $hotelPrice,
            'trip_price' => $tripPrice,
            'surcharge_price' => $surchargePrice,
            'base_total' => $baseTotal,
            'booking_fee_total' => $bookingFeeTotal,
            'final_total_price' => $finalTotalPrice,
        ]);

        return $finalTotalPrice;
    }

    /**
     * Mengupdate booking status.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateBookingStatus($id, $status)
    {
        $booking = $this->findBooking($id);

        if ($booking) {
            $booking->status = $status;
            $booking->save();
            return $booking;
        }
        return null;
    }

    /**
     * Mengambil booking berdasarkan user_id.
     *
     * @param int $userId
     * @return mixed
     */
    public function getBookingsByUserId($userId)
    {
        // Ambil user untuk mendapatkan email
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            return collect([]);
        }
        
        // Query booking berdasarkan user_id atau customer_email (untuk booking lama yang mungkin tidak punya user_id)
        // Gunakan whereRaw untuk case-insensitive comparison
        $bookings = $this->booking->where(function ($query) use ($userId, $user) {
                $query->where('user_id', $userId);
                
                // Jika user punya email, juga cari berdasarkan email (case insensitive)
                if ($user->email) {
                    $query->orWhereRaw('LOWER(customer_email) = LOWER(?)', [$user->email]);
                }
            })
            ->with([
                'trip',
                'trip.assets',
                'tripDuration',
                'tripDuration.tripPrices',
                'boat',
                'boat.assets',
                'cabin',
                'cabin.assets',
                'user',
                'hotelOccupancy',
                'hotelOccupancy.surcharges',
                'additionalFees'
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Update user_id untuk booking yang belum punya user_id (untuk konsistensi data)
        foreach ($bookings as $booking) {
            if (!$booking->user_id && $booking->customer_email && 
                strtolower(trim($booking->customer_email)) === strtolower(trim($user->email))) {
                $booking->user_id = $userId;
                $booking->save();
            }
        }
        
        return $bookings;
    }
}
