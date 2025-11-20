<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BoatController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CabinController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\CarouselController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\EmailBlastController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\HotelOccupanciesController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\EmailBlastRecipientController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FileController;

// Route untuk debugging CORS
Route::get('/cors-test', function () {
    return response()->json([
        'message' => 'CORS test successful',
        'status' => 'success',
        'timestamp' => now()->toIso8601String()
    ]);
});

// Route debugging server
Route::get('/debug-server', function () {
    return response()->json([
        'message' => 'Server debugging successful',
        'status' => 'success',
        'timestamp' => now()->toIso8601String()
    ]);
});

// Authentication routes dengan rate limiting
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:5,1'); // 5 percobaan per menit

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('throttle:3,1'); // 3 percobaan per menit

Route::post('/logout', [
    AuthenticatedSessionController::class,
    'destroy'
])->middleware(['auth:sanctum', 'throttle:10,1']); // 10 percobaan per menit

// Route publik untuk landing page
Route::prefix('landing-page')->group(function () {
    Route::get('/trips', [TripController::class, 'index']);
    Route::get('/trips/{id}', [TripController::class, 'show']);
    Route::get('/highlighted-trips', [TripController::class, 'getHighlightedTrips']);
    Route::get('/testimonials', [TestimonialController::class, 'index']);
    Route::get('/all-testimonials', [TestimonialController::class, 'getAllTestimonials']);
    Route::get('/highlighted-testimonials', [TestimonialController::class, 'getHighlightedTestimonials']);
    Route::get('/google-reviews', [TestimonialController::class, 'getGoogleReviews']);
    Route::get('/boats', [BoatController::class, 'index']);
    Route::get('/boats/{id}', [BoatController::class, 'show']);
    Route::get('/cabins', [CabinController::class, 'index']);
    Route::get('/cabins/{id}', [CabinController::class, 'show']);
    Route::get('/hotels', [HotelOccupanciesController::class, 'index']);
    Route::get('/faq', [FaqController::class, 'index']);
    Route::get('/gallery', [GalleryController::class, 'index']);
    Route::get('/blogs', [BlogController::class, 'index']);
    Route::get('/blogs/{id}', [BlogController::class, 'show']);
    Route::get('/carousels', [CarouselController::class, 'index']);
    Route::get('/carousels/{id}', [CarouselController::class, 'show']);

    // Public Booking Routes
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);

    // Public Transaction Routes
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'check.user.status'])->group(function () {
    // Profile update route - semua user yang terautentikasi bisa update profilnya sendiri
    // Route tanpa ID (menggunakan ID user yang sedang login)
    Route::put('profile', [UserController::class, 'updateProfile']);
    Route::patch('profile', [UserController::class, 'updateProfile']);
    // Route dengan ID (untuk kompatibilitas)
    Route::put('profile/{id}', [UserController::class, 'updateProfile']);
    Route::patch('profile/{id}', [UserController::class, 'updateProfile']);
    
    // Permissions
    Route::middleware('permission:mengelola permissions')->group(function () {
        Route::apiResource('permissions', PermissionController::class);
        Route::patch('permissions/{id}/status', [PermissionController::class, 'updateStatus']);
    });
    // Roles
    Route::middleware('permission:mengelola role')->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::patch('roles/{id}/status', [RoleController::class, 'updateStatus']);
    });
    // Users
    Route::middleware('permission:mengelola user')->group(function () {
        Route::apiResource('users', UserController::class)->except(['update']);
        Route::patch('users/{id}/status', [UserController::class, 'updateStatus']);
    });
    
    // Route khusus untuk update user - dengan pengecekan khusus untuk update profil sendiri
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::patch('users/{id}', [UserController::class, 'update']);
    // Trips
    Route::middleware('permission:mengelola trips')->group(function () {
        Route::apiResource('trips', TripController::class);
        Route::patch('trips/{id}/status', [TripController::class, 'updateStatus']);
    });
    // Customers
    Route::middleware('permission:mengelola customers')->group(function () {
        Route::apiResource('customers', CustomersController::class)->except(['update']);
        Route::patch('customers/{id}/status', [CustomersController::class, 'updateStatus']);
    });
    
    // Route khusus untuk update customer - dengan pengecekan khusus untuk update profil sendiri
    Route::put('customers/{id}', [CustomersController::class, 'update']);
    Route::patch('customers/{id}', [CustomersController::class, 'update']);
    // Hotel Occupancies
    Route::middleware('permission:mengelola hotel_occupancies|melihat hotel occupancy')->group(function () {
        Route::apiResource('hotels', HotelOccupanciesController::class);
        Route::patch('hotels/{id}/status', [HotelOccupanciesController::class, 'updateStatus']);
    });
    // Boats
    Route::middleware('permission:mengelola boats')->group(function () {
        Route::apiResource('boats', BoatController::class);
        Route::patch('boats/{id}/status', [BoatController::class, 'updateStatus']);
    });
    // Cabins
    Route::middleware('permission:mengelola cabins')->group(function () {
        Route::apiResource('cabins', CabinController::class);
        Route::patch('cabins/{id}/status', [CabinController::class, 'updateStatus']);
    });
    // EmailBlast
    Route::middleware('permission:mengelola email_blasts')->group(function () {
        Route::apiResource('emails', EmailBlastController::class);
        Route::patch('emails/{id}/status', [EmailBlastController::class, 'updateStatus']);
    });
    // EmailBlastRecipient
    Route::middleware('permission:mengelola email_blast_recipients')->group(function () {
        Route::apiResource('recipients', EmailBlastRecipientController::class);
        Route::patch('recipients/{id}/status', [EmailBlastRecipientController::class, 'updateStatus']);
    });
    // Subscribers
    Route::middleware('permission:mengelola subscribers')->group(function () {
        Route::apiResource('subscribers', SubscriberController::class);
    });
    // Blog
    Route::middleware('permission:mengelola blogs')->group(function () {
        Route::apiResource('blogs', BlogController::class);
    });

    // Testimonial
    Route::middleware('permission:mengelola testimonials')->group(function () {
        Route::apiResource('testimonials', TestimonialController::class);
    });

    // Faq
    Route::middleware('permission:mengelola faqs')->group(function () {
        Route::apiResource('faqs', FaqController::class);
    });

    // Galleries
    Route::middleware('permission:mengelola galleries')->group(function () {
        Route::apiResource('galleries', GalleryController::class);
        Route::patch('galleries/{id}/status', [GalleryController::class, 'updateStatus']);
    });
    // Generic Assets
    Route::middleware('permission:mengelola assets')->group(function () {
        Route::apiResource('assets', AssetController::class)->except(['index']);
        Route::get('assets', [AssetController::class, 'index']); // Custom index with query parameters
        Route::post('assets/multiple', [AssetController::class, 'storeMultiple']);
    });

    // Public asset serving - tidak memerlukan autentikasi
    Route::get('assets/{id}/serve', [AssetController::class, 'serveImage']);

    // Public file serving dengan keamanan yang lebih baik
    Route::get('files/asset/{id}', [FileController::class, 'serveAsset']);
    Route::get('files/{path}', [FileController::class, 'serveFile'])->where('path', '.*');
    // Bookings - Protected Routes
    Route::middleware('permission:mengelola bookings')->group(function () {
        Route::apiResource('bookings', BookingController::class)->except(['store']);
        Route::patch('bookings/{id}/status', [BookingController::class, 'updateStatus']);
    });
    // Transactions
    Route::middleware('permission:mengelola transactions')->group(function () {
        Route::apiResource('transactions', TransactionController::class);
        Route::patch('transactions/{id}/status', [TransactionController::class, 'updateStatus']);
    });
    // Bank Accounts
    Route::middleware('permission:mengelola bank account')->group(function () {
        Route::apiResource('bank_accounts', BankAccountController::class);
        Route::patch('bank_accounts/{id}/status', [BankAccountController::class, 'updateStatus']);
    });

    Route::middleware('permission:mengelola carousels')->group(function () {
        Route::apiResource('carousels', CarouselController::class);
        Route::patch('carousels/{id}/status', [CarouselController::class, 'updateStatus']);
    });
    
});
Route::get('/index-users', [UserController::class, 'index']);
