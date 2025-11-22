<?php

namespace Database\Seeders;

use App\Models\EmailBlastRecipient;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            UserSeeder::class,
            // CarouselSeeder::class,
            // TripsSeeder::class,
            // ItinerariesSeeder::class,
            // TripDurationSeeder::class,
            // TripPricesSeeder::class,
            // FlightScheduleSeeder::class,
            // CustomersSeeder::class,
            // HotelOccupanciesSeeder::class,
            // BoatSeeder::class,
            // CabinSeeder::class,
            // EmailBlastSeeder::class,
            // EmailBlastRecipientSeeder::class,
            // BlogSeeder::class,
            // SubscriberSeeder::class,
            // TestimonialSeeder::class,
            // FaqSeeder::class,
            // AdditionalFeeSeeder::class,
            // SurchargeSeeder::class,
            // BookingSeeder::class,
            // BankAccountSeeder::class,
            // HotelRequestSeeder::class,
            // TransactionSeeder::class,
            // DetailTransactionSeeder::class,
            // GallerySeeder::class,
            // AssetSeeder::class,
        ]);
    }
}
