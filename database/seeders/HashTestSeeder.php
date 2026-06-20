<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\HashController;
use Illuminate\Http\Request;

class HashTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("=== MEMULAI TEST HASHING (KONTRAK & IOT) ===");

        // 1. Pastikan Disk 'supabase' bisa digunakan (mencegah error jika belum disetup di filesystems.php)
        if (!config('filesystems.disks.supabase')) {
            config(['filesystems.disks.supabase' => [
                'driver' => 'local',
                'root' => storage_path('app/supabase'),
            ]]);
            $this->command->warn("Disk 'supabase' tidak ditemukan di config. Menggunakan penyimpanan lokal sementara untuk test.");
        }

        $macAddress = 'MAC-TEST-' . strtoupper(Str::random(6));

        // 2. Setup User
        $farmer = User::firstOrCreate(
            ['email' => 'farmer.hash@test.com'],
            [
                'name' => 'Petani Hash Test',
                'password' => Hash::make('password'),
                'role' => 'farmer',
                'iot_assigned' => true,
                'iot_sensor_id' => $macAddress,
            ]
        );
        $farmer->update(['iot_sensor_id' => $macAddress]);

        $exporter = User::firstOrCreate(
            ['email' => 'exporter.hash@test.com'],
            [
                'name' => 'Eksportir Hash Test',
                'password' => Hash::make('password'),
                'role' => 'exporter',
            ]
        );

        // 3. Setup Batch
        $batch = Batch::factory()->create([
            'batch_id' => 'PROD-HASH-' . rand(1000, 9999),
            'batch_code' => 'BJI-HASH-' . rand(1000, 9999),
            'farmer_id' => $farmer->id,
            'exporter_id' => $exporter->id,
            'acquired_by' => $exporter->id,
            'status' => 'acquired',
            'farmer_name' => $farmer->name,
            'price' => 15000000,
            'variety' => 'Arabica Test',
            'blockchain_status' => 'pending',
            'name' => 'Kopi Test Hash',
        ]);

        $this->command->info("1. Batch berhasil dibuat: {$batch->batch_id}");

        // 4. Setup PDF Kontrak
        $pdfContent = "<h1>Sertifikat Kontrak Test</h1>
                       <p>Batch: {$batch->batch_id}</p>
                       <p>Petani: {$batch->farmer_name}</p>";
        
        $pdfPath = "batches/certificates/cert-{$batch->id}.pdf";
        
        try {
            $pdf = Pdf::loadHTML($pdfContent);
            Storage::disk('supabase')->put($pdfPath, $pdf->output());
            $batch->update(['certificate_pdf_path' => $pdfPath]);
            $this->command->info("2. PDF berhasil digenerate dan disimpan ke path: {$pdfPath}");
        } catch (\Exception $e) {
            $this->command->error("Gagal membuat/menyimpan PDF: " . $e->getMessage());
        }

        // 5. Setup Data IoT di Supabase DB
        try {
            DB::connection('supabase')->table('esp32_sensor_monitoring')->insert([
                [
                    'mac_address' => $macAddress,
                    'suhu_celsius' => 26.5,
                    'kelembapan_rh' => 70.0,
                    'created_at' => now()->subMinutes(10),
                ],
                [
                    'mac_address' => $macAddress,
                    'suhu_celsius' => 27.0,
                    'kelembapan_rh' => 71.5,
                    'created_at' => now()->subMinutes(5),
                ]
            ]);
            $this->command->info("3. Data sensor IoT berhasil di-insert ke DB Supabase.");
        } catch (\Exception $e) {
            $this->command->error("Gagal koneksi/insert ke DB Supabase: " . $e->getMessage());
            $this->command->warn("Pastikan DB Supabase berjalan, test hash IoT mungkin akan menghasilkan 0 log.");
        }

        // 6. Test Eksekusi HashController secara langsung
        $this->command->info("\n=== HASIL PROSES HASHING ===");
        
        $controller = new HashController();
        $request = Request::create('/api/v1/hashes/generate', 'POST', [
            'batch_id' => $batch->batch_id
        ]);
        
        // Memalsukan user yang login untuk keperluan validasi di controller
        $request->setUserResolver(function () use ($exporter) {
            return $exporter;
        });

        try {
            $response = $controller->generate($request);
            $data = json_decode($response->getContent(), true);

            if ($data['success'] ?? false) {
                $hashData = $data['data'];
                $contract = $hashData['contract_hash'];
                $iot = $hashData['iot_hash'];

                $this->command->line("<fg=green>Berhasil Generate Hash!</>");
                $this->command->line("");
                $this->command->line("<options=bold>-- HASH KONTRAK (PDF) --</>");
                $this->command->line("Hash Value : <fg=yellow>{$contract['hash_value']}</>");
                $this->command->line("PDF Ada?   : " . ($contract['pdf_file_exists'] ? '<fg=green>YA</>' : '<fg=red>TIDAK</>'));
                $this->command->line("Path PDF   : {$contract['certificate_pdf_path']}");
                
                $this->command->line("");
                $this->command->line("<options=bold>-- HASH SENSOR IoT --</>");
                $this->command->line("Hash Value : <fg=yellow>{$iot['hash_value']}</>");
                $this->command->line("Jml Log    : {$iot['log_count']} Data terbaca");
                if (isset($iot['stats']['avg_temperature'])) {
                    $this->command->line("Avg Suhu   : {$iot['stats']['avg_temperature']} °C");
                }

            } else {
                $this->command->error("Gagal generate hash: " . json_encode($data));
            }
        } catch (\Exception $e) {
            $this->command->error("Terjadi error di HashController: " . $e->getMessage());
        }

        $this->command->info("\n=== TEST SELESAI ===");
    }
}
