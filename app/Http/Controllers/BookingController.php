<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\BookingResource;
use App\Http\Requests\BookingStoreRequest;
use App\Http\Requests\BookingUpdateRequest;
use App\Services\Contracts\BookingServiceInterface;
use App\Services\Contracts\TripServiceInterface;
use App\Services\Contracts\CabinServiceInterface;
use App\Services\Contracts\BoatServiceInterface;
use App\Http\Resources\TripResource;
use App\Http\Resources\CabinResource;
use App\Http\Resources\BoatResource;

class BookingController extends Controller
{
    protected $bookingService;
    protected $tripService;
    protected $cabinService;
    protected $boatService;

    public function __construct(BookingServiceInterface $bookingService, TripServiceInterface $tripService, CabinServiceInterface $cabinService, BoatServiceInterface $boatService)
    {
        $this->bookingService = $bookingService;
        $this->tripService = $tripService;
        $this->cabinService = $cabinService;
        $this->boatService = $boatService;
    }

    public function index(Request $request)
    {
        if ($request->has('status')) {
            $status = $request->query('status');
            if (strtolower($status) == '0') {
                $bookings = $this->bookingService->getBookingByStatusPending();
            } elseif (strtolower($status) == '1') {
                $bookings = $this->bookingService->getBookingByStatusConfirmed();
            } elseif (strtolower($status) == '2') {
                $bookings = $this->bookingService->getBookingByStatusCancelled();
            } else {
                return response()->json(['message' => 'Invalid status parameter'], 404);
            }
        } else {
            $bookings = $this->bookingService->getAllBookings();
        }
        return BookingResource::collection($bookings);
    }

    public function show($id)
    {
        $booking = $this->bookingService->getBookingById($id);
        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }
        
        // Jika user sedang melihat booking miliknya sendiri, izinkan tanpa permission
        $user = request()->user();
        if ($user && $booking->user_id && $user->id == $booking->user_id) {
            return new BookingResource($booking);
        }
        
        // Untuk melihat booking lain, cek permission
        if (!$user || !$user->can('mengelola bookings')) {
            return response()->json([
                'message' => 'User does not have the right permissions.'
            ], 403);
        }
        
        return new BookingResource($booking);
    }
    
    /**
     * Get bookings for the authenticated user.
     * - If user has permission "mengelola bookings" (Admin/Super Admin), return all bookings
     * - Otherwise, return only the user's own bookings
     */
    public function myBookings(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        // Jika user punya permission mengelola bookings, kembalikan semua booking
        if ($user->can('mengelola bookings')) {
            if ($request->has('status')) {
                $status = $request->query('status');
                if (strtolower($status) == '0' || strtolower($status) == 'pending') {
                    Cache::forget('booking.pending');
                    $bookings = $this->bookingService->getBookingByStatusPending();
                } elseif (strtolower($status) == '1' || strtolower($status) == 'confirmed') {
                    Cache::forget('booking.confirmed');
                    $bookings = $this->bookingService->getBookingByStatusConfirmed();
                } elseif (strtolower($status) == '2' || strtolower($status) == 'cancelled') {
                    Cache::forget('booking.cancelled');
                    $bookings = $this->bookingService->getBookingByStatusCancelled();
                } else {
                    $bookings = $this->bookingService->getAllBookings(true);
                }
            } else {
                $bookings = $this->bookingService->getAllBookings(true);
            }
        } else {
            // Customer biasa hanya melihat booking mereka sendiri
            // Clear cache untuk user ini
            \Illuminate\Support\Facades\Cache::forget('bookings_user_' . $user->id);
            
            $bookings = $this->bookingService->getBookingsByUserId($user->id);
            
            // Pastikan bookings adalah collection
            if (!($bookings instanceof \Illuminate\Support\Collection)) {
                $bookings = collect($bookings);
            }
            
            // Filter by status if provided
            if ($request->has('status')) {
                $status = $request->query('status');
                $bookings = $bookings->filter(function ($booking) use ($status) {
                    return $booking && isset($booking->status) && $booking->status === $status;
                })->values();
            }
        }
        
        // Pastikan bookings adalah collection
        if (!($bookings instanceof \Illuminate\Support\Collection)) {
            $bookings = collect($bookings);
        }
        
        return BookingResource::collection($bookings);
    }

    public function store(BookingStoreRequest $request)
    {
        $data = $request->all();
        
        // Jika user sedang login, set user_id otomatis
        $user = $request->user();
        if ($user && !isset($data['user_id'])) {
            $data['user_id'] = $user->id;
        } elseif (!isset($data['user_id']) && isset($data['customer_email'])) {
            // Jika user tidak login, coba cari user berdasarkan email
            $userByEmail = \App\Models\User::where('email', $data['customer_email'])->first();
            if ($userByEmail) {
                $data['user_id'] = $userByEmail->id;
            }
        }
        
        $booking = $this->bookingService->createBooking($data);
        if (!$booking) {
            return response()->json(['message' => 'Failed to create booking'], 404);
        }
        return new BookingResource($booking);
    }

    public function update(BookingUpdateRequest $request, $id)
    {
        $booking = $this->bookingService->updateBooking($id, $request->all());
        if (!$booking) {
            return response()->json(['message' => 'Failed to update booking'], 404);
        }
        return new BookingResource($booking);
    }

    public function destroy($id)
    {
        $result = $this->bookingService->deleteBooking($id);
        if (!$result) {
            return response()->json(['message' => 'Failed to delete booking'], 404);
        }
        return response()->json(['message' => 'Booking deleted successfully']);
    }

    /**
     * Update Status Booking.
     */
    public function updateStatus(string $id, Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:Pending,Confirmed,Cancelled',
        ]);

        // Mengirim langsung nilai status, bukan array validated
        $booking = $this->bookingService->updateBookingStatus($id, $validated['status']);

        if (!$booking) {
            return response()->json(['message' => 'Failed to update booking status'], 404);
        }
        return new BookingResource($booking);
    }

    /**
     * Get booking page data
     */
    public function getBookingPage(Request $request)
    {
        $request->validate([
            'type' => 'required|in:open,private',
            'packageId' => 'required|exists:trips,id',
            'date' => 'required|date',
        ]);

        $trip = $this->tripService->getTripById($request->packageId);
        if (!$trip) {
            return response()->json(['message' => 'Trip not found'], 404);
        }

        // Get available cabins for the selected date
        $availableCabins = $this->cabinService->getAvailableCabins($request->date);

        // Get available boats for the selected date
        $availableBoats = $this->boatService->getAvailableBoats($request->date);

        return response()->json([
            'trip' => new TripResource($trip),
            'available_cabins' => CabinResource::collection($availableCabins),
            'available_boats' => BoatResource::collection($availableBoats),
            'selected_date' => $request->date,
            'type' => $request->type
        ]);
    }
}
