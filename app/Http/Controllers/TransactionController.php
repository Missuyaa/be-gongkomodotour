<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\TransactionResource;
use App\Http\Requests\TransactionStoreRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Services\Contracts\TransactionServiceInterface;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionServiceInterface $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->has('status')) {
            $status = $request->query('status');
            if (strtolower($status) == '0') {
                $transactions = $this->transactionService->getWaitingTransactions();
            } elseif (strtolower($status) == '1') {
                $transactions = $this->transactionService->getPaidTransactions();
            } elseif (strtolower($status) == '2') {
                $transactions = $this->transactionService->getRejectedTransactions();
            } else {
                return response()->json(['message' => 'Invalid status parameter'], 404);
            }
        } else {
            $transactions = $this->transactionService->getAllTransactions();
        }
        return TransactionResource::collection($transactions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TransactionStoreRequest $request)
    {
        $transaction = $this->transactionService->createTransaction($request->all());
        if (!$transaction) {
            return response()->json(['message' => 'Failed to create transaction'], 404);
        }
        return new TransactionResource($transaction);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transaction = $this->transactionService->getTransactionById($id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return new TransactionResource($transaction);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TransactionUpdateRequest $request, string $id)
    {
        $transaction = $this->transactionService->updateTransaction($id, $request->all());
        if (!$transaction) {
            return response()->json(['message' => 'Failed to update transaction'], 404);
        }
        return new TransactionResource($transaction);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $result = $this->transactionService->deleteTransaction($id);
        if (!$result) {
            return response()->json(['message' => 'Failed to delete transaction'], 404);
        }
        return response()->json(['message' => 'Transaction deleted successfully']);
    }

    /**
     * Update Status Transaction.
     */
    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Menunggu Pembayaran,Lunas,Ditolak',
        ]);

        $transaction = $this->transactionService->updateTransactionStatus($id, $request->validated());

        if (!$transaction) {
            return response()->json(['message' => 'Failed to update transaction status'], 404);
        }
        return new TransactionResource($transaction);
    }

    /**
     * Get transactions by booking_id for the authenticated user.
     * - If booking_id is provided: return transactions for that booking (with ownership verification for customers)
     * - If booking_id is NOT provided and user is admin: return all transactions (call index method)
     * - If booking_id is NOT provided and user is customer: return error
     */
    public function getTransactionsByBooking(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Jika booking_id tidak ada, dan user adalah admin, panggil method index()
        if (!$request->has('booking_id') || !$request->query('booking_id')) {
            // Jika user punya permission mengelola transactions, kembalikan semua transaksi (seperti index)
            if ($user->can('mengelola transactions')) {
                return $this->index($request);
            } else {
                // Customer tidak bisa melihat semua transaksi tanpa booking_id
                return response()->json([
                    'message' => 'booking_id is required'
                ], 422);
            }
        }

        // Validasi booking_id jika ada
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $bookingId = $request->query('booking_id');

        // Jika user punya permission mengelola transactions, kembalikan semua transaksi untuk booking tersebut
        if ($user->can('mengelola transactions')) {
            $transactions = $this->transactionService->getTransactionsByBookingId($bookingId);
        } else {
            // Customer biasa - verifikasi bahwa booking tersebut milik user
            $booking = \App\Models\Booking::find($bookingId);
            
            if (!$booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            // Cek apakah booking milik user yang login
            if ($booking->user_id && $booking->user_id != $user->id) {
                // Jika tidak ada user_id, cek berdasarkan email
                if (!$booking->user_id && $booking->customer_email) {
                    if (strtolower(trim($booking->customer_email)) !== strtolower(trim($user->email))) {
                        return response()->json([
                            'message' => 'User does not have the right permissions.'
                        ], 403);
                    }
                } else {
                    return response()->json([
                        'message' => 'User does not have the right permissions.'
                    ], 403);
                }
            }

            $transactions = $this->transactionService->getTransactionsByBookingId($bookingId);
        }

        // Pastikan transactions adalah collection
        if (!($transactions instanceof \Illuminate\Support\Collection)) {
            $transactions = collect($transactions);
        }

        return TransactionResource::collection($transactions);
    }
}
