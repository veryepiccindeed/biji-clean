<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\IotData;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IotBatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $macAddress = 'MAC-SIMULATOR-' . strtoupper(Str::random(4));

        // 1. Buat / Update Petani (Farmer)
        $farmer = User::firstOrCreate(
            ['email' => 'petani.iot@biji.test'],
            [
                'name' => 'Petani IoT',
                'password' => Hash::make('password'),
                'role' => 'farmer',
                'phone' => '+62 812-0000-1111',
                'phone_verified' => true,
                'iot_assigned' => true,
                'iot_sensor_id' => $macAddress,
            ]
        );

        // Pastikan mac address tersimpan jika sudah ada user
        if ($farmer->iot_sensor_id !== $macAddress) {
            $farmer->update(['iot_sensor_id' => $macAddress, 'iot_assigned' => true]);
        }

        // 2. Buat / Update Eksportir
        $exporter = User::firstOrCreate(
            ['email' => 'eksportir.iot@biji.test'],
            [
                'name' => 'Eksportir IoT',
                'password' => Hash::make('password'),
                'role' => 'exporter',
                'phone' => '+62 813-0000-2222',
                'phone_verified' => true,
            ]
        );

        // 3. Buat Batch Pertama: Sudah Di-acquire oleh Eksportir (muncul di "Batch Saya")
        $batchMine = Batch::factory()->create([
            'farmer_id' => $farmer->id,
            'exporter_id' => $exporter->id,
            'name' => 'Kopi Gayo Premium - Milik Saya',
            'description' => 'Kopi hasil panen yang sudah di-acquire oleh eksportir.',
            'status' => 'survey_completed',
            'farmer_name' => $farmer->name,
        ]);

        // Buat Batch Kedua: Belum Di-acquire (muncul di "Batch Tersedia")
        $batchAvailable = Batch::factory()->create([
            'farmer_id' => $farmer->id,
            'exporter_id' => null, // Belum di-acquire
            'name' => 'Kopi Gayo Spesial - Tersedia',
            'description' => 'Kopi hasil panen yang masih tersedia untuk di-acquire di marketplace.',
            'status' => 'pending', // Harus 'pending' agar terdeteksi sebagai tersedia
            'farmer_name' => $farmer->name,
        ]);

        $this->command->info("Farmer created with MAC Address: {$macAddress}");
        $this->command->info("Batch Saya created: {$batchMine->batch_code}");
        $this->command->info("Batch Tersedia created: {$batchAvailable->batch_code}");

        // 4. Data IoT (Supabase)
        try {
            // Skenario: Insert dummy data ke tabel esp32_sensor_monitoring
            $monitoringInserted = DB::connection('supabase')->table('esp32_sensor_monitoring')->insert([
                'mac_address' => $macAddress,
                'suhu_celsius' => rand(20, 28) + (rand(0, 10) / 10), // misal 22.5
                'kelembapan_rh' => rand(60, 80) + (rand(0, 10) / 10), // misal 75.2
                'created_at' => now(),
            ]);

            if ($monitoringInserted) {
                $this->command->info("Berhasil meng-insert data dummy ke tabel esp32_sensor_monitoring.");
            }

            // Ambil esp32cam_id pertama yang valid dari esp32cam_monitoring agar tidak melanggar foreign key
            $camId = DB::connection('supabase')->table('esp32cam_monitoring')->value('esp32cam_id') ?? 'CAM001';

            // Memasukkan dummy prediksi ke tabel prediksi_kopi
            $prediksiInserted = DB::connection('supabase')->table('prediksi_kopi')->insert([
                'mac_address' => $macAddress,
                'hasil_prediksi' => 'aman',
                'esp32cam_id' => $camId,
                'created_at' => now(),
            ]);

            if ($prediksiInserted) {
                $this->command->info("Berhasil meng-insert data dummy ke tabel prediksi_kopi.");
            }
        } catch (\Exception $e) {
            $this->command->error("Gagal meng-insert ke Supabase: " . $e->getMessage());
        }
    }
}
