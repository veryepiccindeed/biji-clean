<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_can_access_own_batch_telemetry(): void
    {
        $farmer = User::factory()->create([
            'role' => 'farmer',
            'iot_assigned' => true,
            'iot_sensor_id' => 'MAC-TEST-123'
        ]);

        $batch = Batch::factory()->create([
            'farmer_id' => $farmer->id,
            'status' => 'survey_completed',
        ]);

        // Mock Supabase data
        try {
            DB::connection('supabase')->table('esp32_sensor_monitoring')->insert([
                'mac_address' => 'MAC-TEST-123',
                'suhu_celsius' => 24.5,
                'kelembapan_rh' => 70.2,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Jika driver / database test tidak dikonfigurasi saat phpunit dijalankan, abaikan bagian supabase ini
        }

        $response = $this->actingAs($farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/telemetry");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'data' => [
                    'sensor_id',
                    'logs',
                    'prediction'
                ]
            ]);
    }
}
