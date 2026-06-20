<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\BatchListing;
use App\Models\BatchLog;
use App\Models\BatchSnapshot;
use App\Models\BlockchainLog;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderTimeline;
use App\Models\Port;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Memulai pengisian data dummy (E2E Coffee Supply Chain)...');

        // 1. Master Data: Ports
        $ports = [
            ['name' => 'Belawan', 'full_name' => 'Pelabuhan Belawan, Medan', 'city' => 'Medan', 'eta_days' => 5, 'shipping_rate_per_kg' => 2000],
            ['name' => 'Tanjung Priok', 'full_name' => 'Pelabuhan Tanjung Priok, Jakarta', 'city' => 'Jakarta', 'eta_days' => 3, 'shipping_rate_per_kg' => 1500],
            ['name' => 'Tanjung Perak', 'full_name' => 'Pelabuhan Tanjung Perak, Surabaya', 'city' => 'Surabaya', 'eta_days' => 2, 'shipping_rate_per_kg' => 1200],
        ];

        foreach ($ports as $portData) {
            Port::factory()->create(array_merge($portData, [
                'country' => 'Indonesia',
                'is_active' => true,
            ]));
        }
        $portBelawan = Port::where('name', 'Belawan')->first();
        $this->command->info('1. Ports di-seed.');

        // 2. Fixed User Accounts
        $farmer = User::factory()->create([
            'name' => 'Petani Biji Kopi',
            'email' => 'petani@biji.test',
            'password' => Hash::make('password'),
            'role' => 'farmer',
            'phone' => '+62 812-1111-2222',
            'phone_verified' => true,
            'profile_completion' => 85,
            'iot_assigned' => true,
            'iot_sensor_id' => 'IOT-MAC-SIMULATOR',
            'location' => 'Tana Toraja, Sulawesi Selatan',
            'coordinates' => '-3.1001, 119.8923',
        ]);

        $exporter = User::factory()->create([
            'name' => 'Eksportir Biji Kopi',
            'email' => 'eksportir@biji.test',
            'password' => Hash::make('password'),
            'role' => 'exporter',
            'phone' => '+62 813-3333-4444',
            'profile_completion' => 90,
            'company_name' => 'Gayo Highlands Export Ltd',
        ]);

        $buyer = User::factory()->create([
            'name' => 'Buyer Biji Kopi',
            'email' => 'buyer@biji.test',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'phone' => '+62 814-5555-6666',
            'profile_completion' => 95,
            'company_name' => 'Java Brew Co. Seattle',
        ]);
        $this->command->info('2. Fixed Users di-seed.');

        // 3. Farmer Batches & IoT Logs
        // Batch A (Draft)
        $batchDraft = Batch::factory()->create([
            'farmer_id' => $farmer->id,
            'status' => 'draft',
            'batch_code' => 'BC-DRAFT-1',
        ]);

        // Batch B (Survey Pending)
        $batchSurvey = Batch::factory()->create([
            'farmer_id' => $farmer->id,
            'status' => 'survey_pending',
            'batch_code' => 'BC-SURVEY-2',
        ]);

        // Batch C (Active Processing)
        $batchProcessing = Batch::factory()->create([
            'farmer_id' => $farmer->id,
            'status' => 'processing',
            'batch_code' => 'BC-PROCESS-3',
            'status_jemur' => 'Sedang berjalan',
        ]);

        // Seed Local IoT Logs for Batch C
        for ($i = 0; $i < 24; $i++) {
            BatchLog::factory()->create([
                'batch_id' => $batchProcessing->batch_id,
                'log_type' => 'drying',
                'temperature' => 25 + rand(-3, 5),
                'humidity' => 60 + rand(-10, 15),
                'source' => 'iot',
                'sensor_id' => $farmer->iot_sensor_id,
                'created_at' => Carbon::now()->subHours(24 - $i),
            ]);
        }
        $this->command->info('3. Farmer Batches & Local Logs di-seed.');

        // 4. Exporter Marketplace
        // Batch D (Available)
        $batchAvailable = Batch::factory()->create([
            'farmer_id' => $farmer->id,
            'exporter_id' => null,
            'status' => 'ready',
            'batch_code' => 'BC-AVAIL-4',
            'price' => 15000000,
        ]);

        // Batch E (Acquired & Listed)
        $batchListed = Batch::factory()->create([
            'farmer_id' => $farmer->id,
            'exporter_id' => $exporter->id,
            'status' => 'draft', // Di sisi eksportir jadi draft sebelum deal
            'batch_code' => 'BC-LISTED-5',
            'blockchain_status' => 'published',
            'certificate_pdf_path' => 'certificates/CERT-LISTED-5.pdf',
        ]);

        $listing = BatchListing::factory()->create([
            'exporter_id' => $exporter->id,
            'batch_code' => $batchListed->batch_code,
            'status' => 'listed',
            'stock_kg' => 1200,
            'price_per_kg' => 135000,
        ]);

        BatchSnapshot::factory()->create([
            'batch_listing_id' => $listing->id,
            'batch_code' => $listing->batch_code,
        ]);

        // Seed Blockchain logs for Exporter
        BlockchainLog::factory()->success()->create(['exporter_id' => $exporter->id, 'batch_id' => $batchListed->id, 'batch_code' => $batchListed->batch_code]);
        BlockchainLog::factory()->failed()->create(['exporter_id' => $exporter->id, 'batch_id' => $batchListed->id, 'batch_code' => $batchListed->batch_code, 'retryable' => true]);
        
        $this->command->info('4. Exporter Marketplace & Blockchain Logs di-seed.');

        // 5. Buyer Orders
        // Order 1: Pending Payment
        Order::factory()->create([
            'buyer_id' => $buyer->id,
            'exporter_id' => $exporter->id,
            'batch_id' => $batchListed->id,
            'batch_listing_id' => $listing->id,
            'port_id' => $portBelawan->id,
            'status' => 'pending_payment',
            'status_label' => 'Menunggu Pembayaran',
        ]);

        // Order 2: Payment Verifying
        Order::factory()->create([
            'buyer_id' => $buyer->id,
            'exporter_id' => $exporter->id,
            'batch_id' => $batchListed->id,
            'batch_listing_id' => $listing->id,
            'port_id' => $portBelawan->id,
            'status' => 'payment_verifying',
            'status_label' => 'Verifikasi Pembayaran',
            'payment_proof' => 'proofs/transfer.jpg',
        ]);

        // Order 3: In Transit
        $orderTransit = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'exporter_id' => $exporter->id,
            'batch_id' => $batchListed->id,
            'batch_listing_id' => $listing->id,
            'port_id' => $portBelawan->id,
            'status' => 'in_transit',
            'status_label' => 'Dalam Pengiriman',
        ]);
        OrderTimeline::factory()->create(['order_id' => $orderTransit->order_id, 'status' => 'pending_payment', 'is_current' => false, 'timestamp' => Carbon::now()->subDays(3)]);
        OrderTimeline::factory()->create(['order_id' => $orderTransit->order_id, 'status' => 'paid', 'is_current' => false, 'timestamp' => Carbon::now()->subDays(2)]);
        OrderTimeline::factory()->create(['order_id' => $orderTransit->order_id, 'status' => 'shipped', 'is_current' => true, 'timestamp' => Carbon::now()->subDays(1)]);

        // Order 4: Completed
        $orderCompleted = Order::factory()->completed()->create([
            'buyer_id' => $buyer->id,
            'exporter_id' => $exporter->id,
            'batch_id' => $batchListed->id,
            'batch_listing_id' => $listing->id,
            'port_id' => $portBelawan->id,
        ]);
        OrderDocument::factory()->create(['order_id' => $orderCompleted->order_id, 'type' => 'invoice']);

        $this->command->info('5. Buyer Orders di-seed.');

        // 6. Notifications
        Notification::factory()->create(['user_id' => $farmer->id, 'title' => 'Batch Selesai Di-survey', 'message' => 'Batch Anda telah lolos survey.']);
        Notification::factory()->create(['user_id' => $exporter->id, 'title' => 'Pesanan Baru', 'message' => 'Ada pesanan masuk dari Java Brew Co.']);
        Notification::factory()->create(['user_id' => $buyer->id, 'title' => 'Pesanan Dikirim', 'message' => 'Pesanan kopi Anda telah diberangkatkan via Pelabuhan Belawan.']);
        
        $this->command->info('6. Notifications di-seed.');

        // 7. Supabase IoT Data Seeding (Aman dari koneksi error)
        try {
            $this->command->info('7. Menghubungkan ke Supabase...');
            
            $supabaseMac = $farmer->iot_sensor_id;
            $dataToInsert = [];
            
            for ($i = 0; $i < 24; $i++) {
                $dataToInsert[] = [
                    'mac_address' => $supabaseMac,
                    'suhu_celsius' => rand(22, 28) + (rand(0, 10) / 10),
                    'kelembapan_rh' => rand(60, 80) + (rand(0, 10) / 10),
                    'created_at' => Carbon::now()->subHours(24 - $i)->toIso8601String(),
                ];
            }

            DB::connection('supabase')->table('esp32_sensor_monitoring')->insert($dataToInsert);
            $this->command->info("Data esp32_sensor_monitoring berhasil di-seed (24 rows).");

            DB::connection('supabase')->table('prediksi_kopi')->insert([
                'mac_address' => $supabaseMac,
                'hasil_prediksi' => 'aman',
                'esp32cam_id' => 'CAM-SIMULATOR',
                'created_at' => Carbon::now()->toIso8601String(),
            ]);
            $this->command->info("Data prediksi_kopi berhasil di-seed.");

        } catch (\Exception $e) {
            $this->command->warn("Gagal meng-insert ke Supabase: " . $e->getMessage());
            $this->command->warn("Seeding lokal tetap dilanjutkan tanpa data Supabase aktual.");
        }

        $this->command->info('Seeding selesai dengan sukses!');
    }
}
