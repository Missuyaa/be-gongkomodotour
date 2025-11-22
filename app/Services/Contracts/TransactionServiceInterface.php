<?php

namespace App\Services\Contracts;

interface TransactionServiceInterface
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
     * Mengambil semua transaksi yang Menunggu Pembayaran.
     *
     * @return mixed
     */
    public function getWaitingTransactions();

    /**
     * Mengambil semua transaksi yang Lunas.
     *
     * @return mixed
     */
    public function getPaidTransactions();

    /**
     * Mengambil semua transaksi yang Ditolak.
     *
     * @return mixed
     */
    public function getRejectedTransactions();

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
     * Mengupdate status transaksi.
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
