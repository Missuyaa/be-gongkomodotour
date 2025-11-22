<?php

namespace App\Services\Implementations;

use App\Models\Booking;
use App\Models\Surcharge;
use App\Models\HotelRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\Contracts\TransactionServiceInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;

class TransactionService implements TransactionServiceInterface
{
    /**
     * @var TransactionRepositoryInterface
     */
    protected $repository;

    const TRANSACTIONS_ALL_CACHE_KEY = 'transactions.all';
    const TRANSACTIONS_WAITING_CACHE_KEY = 'transactions.waiting';
    const TRANSACTIONS_PAID_CACHE_KEY = 'transactions.paid';
    const TRANSACTIONS_REJECTED_CACHE_KEY = 'transactions.rejected';

    /**
     * @param TransactionRepositoryInterface $repository
     */
    public function __construct(TransactionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Mengambil semua transaksi.
     *
     * @return mixed
     */
    public function getAllTransactions()
    {
        return Cache::remember(self::TRANSACTIONS_ALL_CACHE_KEY, 3600, function () {
            return $this->repository->getAllTransactions();
        });
    }

    /**
     * Mengambil transaksi berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getTransactionById($id)
    {
        return $this->repository->getTransactionById($id);
    }

    /**
     * Mengambil transaksi berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getTransactionByName($name)
    {
        return $this->repository->getTransactionByName($name);
    }

    /**
     * Mengambil transaksi berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getTransactionByStatus($status)
    {
        return $this->repository->getTransactionByStatus($status);
    }

    /**
     * Mengambil semua transaksi yang Menunggu Pembayaran.
     *
     * @return mixed
     */
    public function getWaitingTransactions()
    {
        return Cache::remember(self::TRANSACTIONS_WAITING_CACHE_KEY, 3600, function () {
            return $this->repository->getTransactionByStatus('Menunggu Pembayaran');
        });
    }

    /**
     * Mengambil semua transaksi yang Lunas.
     *
     * @return mixed
     */
    public function getPaidTransactions()
    {
        return Cache::remember(self::TRANSACTIONS_PAID_CACHE_KEY, 3600, function () {
            return $this->repository->getTransactionByStatus('Lunas');
        });
    }

    /**
     * Mengambil semua transaksi yang Ditolak.
     *
     * @return mixed
     */
    public function getRejectedTransactions()
    {
        return Cache::remember(self::TRANSACTIONS_REJECTED_CACHE_KEY, 3600, function () {
            return $this->repository->getTransactionByStatus('Ditolak');
        });
    }

    /**
     * Membuat transaksi baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createTransaction(array $data)
    {
        try {
            Log::info('Creating transaction with data:', $data);

            $transaction = $this->repository->createTransaction($data);
            if (!$transaction) {
                Log::error('Failed to create transaction in repository');
                return false;
            }

            Log::info('Transaction created successfully with ID: ' . $transaction->id);

            // Proses upload file assets jika ada
            if (isset($data['assets']) && is_array($data['assets'])) {
                Log::info('Processing assets upload');
                foreach ($data['assets'] as $assetData) {
                    if (isset($assetData['file'])) {
                        $file = $assetData['file'];
                        $path = $file->store('assets/transactions', 'public');

                        $transaction->assets()->create([
                            'title' => $assetData['title'],
                            'description' => $assetData['description'] ?? null,
                            'file_path' => $path,
                            'file_url' => asset('storage/' . $path),
                            'is_external' => $assetData['is_external'] ?? false,
                        ]);
                    }
                }
            }

            // Membuat detail transaksi untuk Hotel Request jika data tersedia
            if (isset($data['hotel_request_details']) && is_array($data['hotel_request_details'])) {
                Log::info('Processing hotel request details');
                foreach ($data['hotel_request_details'] as $detail) {
                    // Jika hotel_request_id tidak ada pada payload, maka buat HotelRequest baru
                    if (!isset($detail['hotel_request_id'])) {
                        Log::info('Creating new hotel request');
                        $hotelRequest = HotelRequest::create([
                            'transaction_id'       => $transaction->id,
                            'user_id'              => Auth::id(),
                            'confirmed_note'       => $detail['confirmed_note'] ?? '',
                            'requested_hotel_name' => $detail['requested_hotel_name'] ?? '',
                            'request_status'       => 'Menunggu Konfirmasi',
                            'confirmed_price'      => $detail['confirmed_price'] ?? 0,
                        ]);
                        $detail['hotel_request_id'] = $hotelRequest->id;
                        Log::info('Hotel request created with ID: ' . $hotelRequest->id);
                    }

                    $transaction->details()->create([
                        'amount'         => $detail['amount'] ?? 0,
                        'description'    => $detail['description'] ?? 'Payment for Hotel Request',
                        'reference_id'   => $detail['hotel_request_id'],
                        'reference_type' => \App\Models\HotelRequest::class,
                        'type'           => 'Additional Fee'
                    ]);
                }
            }

            // Pengecekan otomatis surcharge berdasarkan tanggal booking
            $booking = Booking::find($data['booking_id']);
            if ($booking) {
                Log::info('Checking for matching surcharge for booking dates: ' . $booking->start_date . ' to ' . $booking->end_date);
                $matchingSurcharge = Surcharge::where('start_date', $booking->start_date)
                    ->where('end_date', $booking->end_date)
                    ->first();

                if ($matchingSurcharge) {
                    Log::info('Found matching surcharge with ID: ' . $matchingSurcharge->id);
                    $transaction->details()->create([
                        'amount'         => $matchingSurcharge->amount,
                        'description'    => 'Automatically added surcharge based on booking dates',
                        'reference_id'   => $matchingSurcharge->id,
                        'reference_type' => \App\Models\Surcharge::class,
                        'type'           => 'Surcharge'
                    ]);
                }
            }

            // Jika masih ada data surcharge_details yang dikirim secara manual, proses juga
            if (isset($data['surcharge_details']) && is_array($data['surcharge_details'])) {
                Log::info('Processing manual surcharge details');
                foreach ($data['surcharge_details'] as $detail) {
                    $transaction->details()->create([
                        'amount'         => $detail['amount'] ?? 0,
                        'description'    => $detail['description'] ?? 'Payment for Surcharge',
                        'reference_id'   => $detail['surcharge_id'],
                        'reference_type' => \App\Models\Surcharge::class,
                        'type'           => 'Surcharge'
                    ]);
                }
            }

            $this->clearTransactionCaches();
            // Muat relasi 'details' sebelum mengembalikannya
            $result = $transaction->load('details', 'booking', 'assets');
            Log::info('Transaction process completed successfully');
            return $result;
        } catch (\Exception $e) {
            Log::error('Error creating transaction: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Memperbarui transaksi berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateTransaction($id, array $data)
    {
        $transaction = $this->repository->getTransactionById($id);
        if ($transaction) {
            $this->repository->updateTransaction($id, $data);

            // Proses upload file assets jika ada
            if (isset($data['assets']) && is_array($data['assets'])) {
                Log::info('Processing assets upload for update');
                foreach ($data['assets'] as $assetData) {
                    if (isset($assetData['file'])) {
                        $file = $assetData['file'];
                        $path = $file->store('assets/transactions', 'public');

                        $transaction->assets()->create([
                            'title' => $assetData['title'],
                            'description' => $assetData['description'] ?? null,
                            'file_path' => $path,
                            'file_url' => asset('storage/' . $path),
                            'is_external' => $assetData['is_external'] ?? false,
                        ]);
                    }
                }
            }

            // Hapus semua detail transaksi lama terlebih dahulu
            $transaction->details()->delete();

            // Buat kembali detail transaksi untuk Hotel Request jika data tersedia
            if (isset($data['hotel_request_details']) && is_array($data['hotel_request_details'])) {
                foreach ($data['hotel_request_details'] as $detail) {
                    // Cek apakah hotel_request_id tersedia, jika tidak, buat HotelRequest baru
                    if (!isset($detail['hotel_request_id'])) {
                        $hotelRequest = HotelRequest::create([
                            'transaction_id'       => $transaction->id,
                            'user_id'              => Auth::id(),
                            'confirmed_note'       => $detail['confirmed_note'] ?? '',
                            'requested_hotel_name' => $detail['requested_hotel_name'] ?? '',
                            'request_status'       => 'Menunggu Konfirmasi',
                            'confirmed_price'      => $detail['confirmed_price'] ?? 0,
                        ]);
                        $detail['hotel_request_id'] = $hotelRequest->id;
                    }
                    $transaction->details()->create([
                        'amount'         => $detail['amount'] ?? 0,
                        'description'    => $detail['description'] ?? 'Payment for Hotel Request',
                        'reference_id'   => $detail['hotel_request_id'],
                        'reference_type' => \App\Models\HotelRequest::class,
                        'type'           => 'Additional Fee'
                    ]);
                }
            }

            // Proses otomatis surcharge berdasarkan tanggal booking
            $booking = Booking::find($data['booking_id']);
            if ($booking) {
                $matchingSurcharge = Surcharge::where('start_date', $booking->start_date)
                    ->where('end_date', $booking->end_date)
                    ->first();

                if ($matchingSurcharge) {
                    $transaction->details()->create([
                        'amount'         => $matchingSurcharge->amount,
                        'description'    => 'Automatically added surcharge based on booking dates',
                        'reference_id'   => $matchingSurcharge->id,
                        'reference_type' => \App\Models\Surcharge::class,
                        'type'           => 'Surcharge'
                    ]);
                }
            }

            // Proses surcharge_details jika disediakan secara manual
            if (isset($data['surcharge_details']) && is_array($data['surcharge_details'])) {
                foreach ($data['surcharge_details'] as $detail) {
                    $transaction->details()->create([
                        'amount'         => $detail['amount'] ?? 0,
                        'description'    => $detail['description'] ?? 'Payment for Surcharge',
                        'reference_id'   => $detail['surcharge_id'],
                        'reference_type' => \App\Models\Surcharge::class,
                        'type'           => 'Surcharge'
                    ]);
                }
            }

            $this->clearTransactionCaches();
            // Muat relasi 'details' sebelum mengembalikannya
            return $transaction->load('details', 'booking', 'assets');
        }
        return false;
    }

    /**
     * Menghapus transaksi berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteTransaction($id)
    {
        $transaction = $this->repository->getTransactionById($id);
        if ($transaction) {
            $this->repository->deleteTransaction($id);
            $this->clearTransactionCaches();
            return true;
        }
        return false;
    }

    public function updateTransactionStatus($id, $status)
    {
        $transaction = $this->getTransactionById($id);

        if ($transaction) {
            $result = $this->repository->updateTransactionStatus($id, $status);

            $this->clearTransactionCaches();

            return $result;
        }

        return null;
    }

    /**
     * Mengambil transaksi berdasarkan booking_id.
     *
     * @param int $bookingId
     * @return mixed
     */
    public function getTransactionsByBookingId($bookingId)
    {
        return $this->repository->getTransactionsByBookingId($bookingId);
    }

    /**
     * Menghapus semua cache transaksi
     *
     * @param int|null $id
     * @return void
     */
    public function clearTransactionCaches()
    {
        Cache::forget(self::TRANSACTIONS_ALL_CACHE_KEY);
        Cache::forget(self::TRANSACTIONS_WAITING_CACHE_KEY);
        Cache::forget(self::TRANSACTIONS_PAID_CACHE_KEY);
        Cache::forget(self::TRANSACTIONS_REJECTED_CACHE_KEY);
    }
}
