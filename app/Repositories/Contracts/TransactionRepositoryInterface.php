<?php

namespace App\Repositories\Contracts;

interface TransactionRepositoryInterface
{
    /**
     * Mengambil semua transaksi.
     *
     * @return mixed
     */
    public function getAllTransactions();

    /**
     * Mengambil transaksi berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getTransactionById($id);

    /**
     * Mengambil transaksi berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getTransactionByName($name);

    /**
     * Mengambil transaksi berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getTransactionByStatus($status);

    /**
     * Membuat transaksi baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createTransaction(array $data);

    /**
     * Memperbarui transaksi berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateTransaction($id, array $data);

    /**
     * Menghapus transaksi berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteTransaction($id);

    /**
     * Mengupdate transaksi status.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateTransactionStatus($id, $status);

    /**
     * Mengambil transaksi berdasarkan booking_id.
     *
     * @param int $bookingId
     * @return mixed
     */
    public function getTransactionsByBookingId($bookingId);
}
