<?php

namespace App\Repositories\Eloquent;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Contracts\TransactionRepositoryInterface;

class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * @var Transaction
     */
    protected $model;

    /**
     * Constructor
     *
     * @param Transaction $model
     */
    public function __construct(Transaction $model)
    {
        $this->model = $model;
    }

    /**
     * Mengambil semua transaksi.
     *
     * @return mixed
     */
    public function getAllTransactions()
    {
        return $this->model->with('details', 'booking', 'assets')->get();
    }

    /**
     * Mengambil transaksi berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getTransactionById($id)
    {
        try {
            return $this->model->with('details', 'booking', 'assets')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Transaction with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Mengambil transaksi berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getTransactionByName($name)
    {
        return $this->model->with('details', 'booking', 'assets')->where('name', $name)->first();
    }

    /**
     * Mengambil transaksi berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getTransactionByStatus($status)
    {
        return $this->model->with('details', 'booking', 'assets')->where('payment_status', $status)->get();
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
            Log::info('Repository: Attempting to create transaction');
            Log::info('Repository: Transaction data:', $data);

            $transaction = $this->model->create($data);

            if ($transaction) {
                Log::info('Repository: Transaction created successfully with ID: ' . $transaction->id);
                return $transaction;
            }

            Log::error('Repository: Failed to create transaction - no error but transaction is null');
            return null;
        } catch (\Exception $e) {
            Log::error("Repository: Failed to create transaction: {$e->getMessage()}");
            Log::error("Repository: Stack trace: " . $e->getTraceAsString());
            return null;
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
        $transaction = $this->findTransaction($id);
        if (!$transaction) {
            return null;
        }
        try {
            $transaction->update($data);
            return $transaction->fresh(['details', 'booking']);
        } catch (\Exception $e) {
            Log::error("Failed to update transaction with ID {$id}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Menghapus transaksi berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteTransaction($id)
    {
        $transaction = $this->findTransaction($id);
        if (!$transaction) {
            return null;
        }
        try {
            $transaction->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete transaction with ID {$id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Helper method untuk menemukan transaksi berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    protected function findTransaction($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Transaction with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Mengupdate transaksi status.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateTransactionStatus($id, $status)
    {
        $transaction = $this->findTransaction($id);

        if ($transaction) {
            $transaction->payment_status = $status;
            $transaction->save();
            return $transaction->fresh(['details', 'booking']);
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
        return $this->model->with('details', 'booking', 'assets')
            ->where('booking_id', $bookingId)
            ->get();
    }
}
