<?php

namespace App\Repositories\Contracts;

interface BookingRepositoryInterface
{
    /**
     * Mengambil semua bookings.
     *
     * @return mixed
     */
    public function getAllBookings();

    /**
     * Mengambil booking berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getBookingById($id);

    /**
     * Mengambil booking berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getBookingByName($name);

    /**
     * Mengambil booking berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getBookingByStatus($status);

    /**
     * Membuat booking baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createBooking(array $data);

    /**
     * Memperbarui booking berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateBooking($id, array $data);

    /**
     * Menghapus booking berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteBooking($id);

    /**
     * Mengupdate booking status.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateBookingStatus($id, $status);

    /**
     * Mengambil booking berdasarkan user_id.
     *
     * @param int $userId
     * @return mixed
     */
    public function getBookingsByUserId($userId);
}
