<?php

namespace App\Services\Implementations;

use Illuminate\Support\Facades\Cache;
use App\Services\Contracts\BookingServiceInterface;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\AdditionalFeeRepositoryInterface;

class BookingService implements BookingServiceInterface
{
    protected $bookingRepository;
    protected $additionalFeeRepository;

    const BOOKING_ALL_CACHE_KEY = 'booking.all';
    const BOOKING_ACTIVE_CACHE_KEY = 'booking.active';
    const BOOKING_INACTIVE_CACHE_KEY = 'booking.inactive';
    const BOOKING_PENDING_CACHE_KEY = 'booking.pending';
    const BOOKING_CONFIRMED_CACHE_KEY = 'booking.confirmed';
    const BOOKING_CANCELLED_CACHE_KEY = 'booking.cancelled';

    /**
     * Konstruktor BookingService.
     *
     * @param BookingRepositoryInterface $bookingRepository
     * @param AdditionalFeeRepositoryInterface $additionalFeeRepository
     */
    public function __construct(
        BookingRepositoryInterface $bookingRepository,
        AdditionalFeeRepositoryInterface $additionalFeeRepository
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->additionalFeeRepository = $additionalFeeRepository;
    }

    /**
     * Mengambil semua bookings.
     *
     * @param bool $forceRefresh Force refresh dari database (bypass cache)
     * @return mixed
     */
    public function getAllBookings($forceRefresh = false)
    {
        if ($forceRefresh) {
            Cache::forget(self::BOOKING_ALL_CACHE_KEY);
        }
        
        return Cache::remember(self::BOOKING_ALL_CACHE_KEY, 3600, function () {
            return $this->bookingRepository->getAllBookings();
        });
    }

    /**
     * Mengambil booking berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getBookingById($id)
    {
        return $this->bookingRepository->getBookingById($id);
    }

    /**
     * Mengambil booking berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getBookingByName($name)
    {
        return $this->bookingRepository->getBookingByName($name);
    }

    /**
     * Mengambil booking berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getBookingByStatus($status)
    {
        return $this->bookingRepository->getBookingByStatus($status);
    }

    /**
     * Mengambil booking berdasarkan status pending.
     *
     * @return mixed
     */
    public function getBookingByStatusPending()
    {
        return Cache::remember(self::BOOKING_PENDING_CACHE_KEY, 3600, function () {
            return $this->bookingRepository->getBookingByStatus('Pending');
        });
    }

    /**
     * Mengambil booking berdasarkan status confirmed.
     *
     * @return mixed
     */
    public function getBookingByStatusConfirmed()
    {
        return Cache::remember(self::BOOKING_CONFIRMED_CACHE_KEY, 3600, function () {
            return $this->bookingRepository->getBookingByStatus('Confirmed');
        });
    }

    /**
     * Mengambil booking berdasarkan status cancelled.
     *
     * @return mixed
     */
    public function getBookingByStatusCancelled()
    {
        return Cache::remember(self::BOOKING_CANCELLED_CACHE_KEY, 3600, function () {
            return $this->bookingRepository->getBookingByStatus('Cancelled');
        });
    }

    /**
     * Membuat booking baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createBooking(array $data)
    {
        // Data untuk relasi many-to-many diharapkan sudah menggunakan struktur yang tepat,
        // misalnya: cabin_ids, boat_ids, dan additional_fee_ids (berupa array ID atau array asosiatif)
        $booking = $this->bookingRepository->createBooking($data);
        if ($booking) {
            // Sinkronisasi additional fees (baik fee wajib dan optional)
            $this->syncAdditionalFees($booking, $data);
            $this->clearBookingCaches($booking->user_id ?? null);
        }
        return $booking;
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
        $booking = $this->bookingRepository->updateBooking($id, $data);
        if ($booking) {
            $this->syncAdditionalFees($booking, $data);
            $this->clearBookingCaches($booking->user_id ?? null);
        }
        return $booking;
    }

    /**
     * Menghapus booking berdasarkan ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteBooking($id)
    {
        $booking = $this->getBookingById($id);
        $userId = $booking ? ($booking->user_id ?? null) : null;
        $result = $this->bookingRepository->deleteBooking($id);
        $this->clearBookingCaches($userId);
        return $result;
    }

    /**
     * Sinkronisasi additional fees berdasarkan fee wajib (berdasarkan trip_id)
     * dan optional fee jika ada input "additional_fee_ids".
     *
     * Metode ini menggabungkan kedua jenis fee dan menyinkronisasikannya ke pivot table.
     *
     * @param mixed $booking
     * @param array $data
     */
    protected function syncAdditionalFees($booking, array $data)
    {
        $syncFees = [];

        // Proses fee wajib berdasarkan trip_id booking
        if ($booking->trip_id) {
            $cacheKey = 'additional_fee_trip_' . $booking->trip_id;
            $requiredFees = Cache::remember($cacheKey, 3600, function () use ($booking) {
                return $this->additionalFeeRepository->getAdditionalFeesByTripId($booking->trip_id);
            });

            if ($requiredFees) {
                foreach ($requiredFees as $fee) {
                    if ($fee->is_required && $fee->status === 'Aktif') {
                        $syncFees[$fee->id] = ['total_price' => $fee->price];
                    }
                }
            }
        }

        // Proses fee optional dari input
        if (isset($data['additional_fee_ids']) && is_array($data['additional_fee_ids'])) {
            foreach ($data['additional_fee_ids'] as $feeData) {
                if (is_array($feeData)) {
                    $fee = $this->additionalFeeRepository->getAdditionalFeeById($feeData['additional_fee_id']);
                    if ($fee && !$fee->is_required && $fee->status === 'Aktif') {
                        $syncFees[$fee->id] = ['total_price' => $feeData['total_price'] ?? $fee->price];
                    }
                } else {
                    $fee = $this->additionalFeeRepository->getAdditionalFeeById($feeData);
                    if ($fee && !$fee->is_required && $fee->status === 'Aktif') {
                        $syncFees[$fee->id] = ['total_price' => $fee->price];
                    }
                }
            }
        }

        if (!empty($syncFees)) {
            $booking->additionalFees()->sync($syncFees);
        }
    }

    public function updateBookingStatus($id, $status)
    {
        $booking = $this->getBookingById($id);

        if ($booking) {
            $result = $this->bookingRepository->updateBookingStatus($id, $status);

            $this->clearBookingCaches($booking->user_id ?? null);

            return $result;
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
        $cacheKey = 'bookings_user_' . $userId;
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            return $this->bookingRepository->getBookingsByUserId($userId);
        });
    }

    /**
     * Menghapus semua cache booking
     *
     * @param int|null $userId
     * @return void
     */
    public function clearBookingCaches($userId = null)
    {
        Cache::forget(self::BOOKING_ALL_CACHE_KEY);
        Cache::forget(self::BOOKING_ACTIVE_CACHE_KEY);
        Cache::forget(self::BOOKING_PENDING_CACHE_KEY);
        Cache::forget(self::BOOKING_CONFIRMED_CACHE_KEY);
        Cache::forget(self::BOOKING_CANCELLED_CACHE_KEY);
        
        if ($userId) {
            Cache::forget('bookings_user_' . $userId);
        }
    }
}
