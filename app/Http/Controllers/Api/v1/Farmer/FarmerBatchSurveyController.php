<?php

namespace App\Http\Controllers\Api\v1\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchPhoto;
use Illuminate\Http\Request;

class FarmerBatchSurveyController extends Controller
{
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where('batch_id', $id)->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Batch tidak ditemukan',
            ], 404);
        }

        if ($batch->farmer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'code' => 'BATCH_NOT_OWNED',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $photoCount = BatchPhoto::where('batch_id', $batch->batch_id)->count();

        $surveyStatus = 'not_submitted';
        $surveyStatusLabel = 'Belum Diajukan';

        switch ($batch->status) {
            case 'draft':
                $surveyStatus = 'not_submitted';
                $surveyStatusLabel = 'Belum Diajukan';
                break;
            case 'survey_pending':
            case 'remote_review':
                $surveyStatus = 'pending';
                $surveyStatusLabel = $user->iot_assigned ? 'Menunggu Approval Remote' : 'Menunggu Jadwal Survey';
                break;
            case 'survey_scheduled':
                $surveyStatus = 'scheduled';
                $surveyStatusLabel = 'Survey Terjadwal';
                break;
            case 'survey_in_progress':
                $surveyStatus = 'in_progress';
                $surveyStatusLabel = 'Sedang Disurvey';
                break;
            case 'survey_completed':
                $surveyStatus = 'completed';
                $surveyStatusLabel = 'Survey Selesai';
                break;
            case 'rejected':
                $surveyStatus = 'rejected';
                $surveyStatusLabel = 'Ditolak';
                break;
            case 'cancelled':
                $surveyStatus = 'cancelled';
                $surveyStatusLabel = 'Dibatalkan';
                break;
            default:
                $surveyStatus = 'completed';
                $surveyStatusLabel = 'Selesai';
                break;
        }

        $iotStatus = 'not_installed';
        $iotStatusLabel = 'Belum Terpasang';
        $installedAt = null;
        $sensorId = null;

        if ($user->iot_assigned) {
            $iotStatus = 'installed';
            $iotStatusLabel = 'Sudah Terpasang';
            $installedAt = now()->toIso8601String();
            $sensorId = $user->iot_sensor_id;
        }

        $survey = [
            'status' => $surveyStatus,
            'status_label' => $surveyStatusLabel,
            'submitted_at' => $batch->status !== 'draft' ? $batch->updated_at->toIso8601String() : null,
            'is_first_survey' => ! $user->iot_assigned,
            'iot_installation_included' => ! $user->iot_assigned,
            'scheduled_date' => null,
            'scheduled_date_label' => null,
            'scheduled_time' => null,
            'surveyor_name' => null,
            'notes' => null,
            'result' => null,
            'completed_at' => null,
            'coordinates' => ['lat' => null, 'lng' => null],
        ];

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Status survey berhasil diambil',
            'data' => [
                'batch' => [
                    'id' => $batch->batch_id,
                    'code' => $batch->batch_code,
                    'status' => $batch->status,
                    'survey_status' => 'Menunggu Approval Remote',
                ],
                'prerequisites' => [
                    'photos_minimum_met' => $photoCount >= 3,
                    'photo_count' => $photoCount,
                    'profile_completion_met' => $user->profile_completion >= 50,
                    'profile_completion' => $user->profile_completion,
                    'phone_verified' => $user->phone_verified == 1,
                    'is_ready_to_submit' => $photoCount >= 3 && $user->profile_completion >= 50 && $user->phone_verified == 1 && $batch->status === 'draft',
                ],
                'survey' => $survey,
                'iot' => [
                    'status' => $iotStatus,
                    'status_label' => $iotStatusLabel,
                    'installed_at' => $installedAt,
                    'sensor_id' => $sensorId,
                    'last_reading_at' => null,
                    'coordinates' => $user->coordinates,
                    'elevation_mdpl' => $batch->elevation_mdpl ? (int) $batch->elevation_mdpl : 0,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function submit(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where('batch_id', $id)->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Batch tidak ditemukan',
            ], 404);
        }

        if ($batch->farmer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'code' => 'BATCH_NOT_OWNED',
                'message' => 'Akses ditolak',
            ], 403);
        }

        if ($batch->status === 'survey_pending') {
            return response()->json([
                'success' => false,
                'code' => 'BATCH_ALREADY_SUBMITTED',
                'message' => 'Survey batch ini sudah diajukan',
            ], 409);
        }

        if ($batch->status !== 'draft') {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
                'message' => 'Hanya batch draft yang dapat diajukan survey',
            ], 400);
        }

        $photoCount = BatchPhoto::where('batch_id', $batch->batch_id)->count();

        if ($photoCount < 3) {
            return response()->json([
                'success' => false,
                'code' => 'BATCH_PHOTO_MINIMUM',
                'message' => 'Minimal 3 foto dokumentasi dibutuhkan',
            ], 422);
        }

        if ($user->profile_completion < 50) {
            return response()->json([
                'success' => false,
                'code' => 'PROFILE_INCOMPLETE',
                'message' => 'Lengkapi profil minimal 50%',
            ], 403);
        }

        if (! $user->phone_verified) {
            return response()->json([
                'success' => false,
                'code' => 'PHONE_NOT_VERIFIED',
                'message' => 'Nomor HP harus diverifikasi',
            ], 403);
        }

        $batch->status = 'survey_pending';
        $batch->save();

        $data = [
            'batch' => [
                'id' => $batch->batch_id,
                'code' => $batch->batch_code,
                'stage' => $user->iot_assigned ? 'Approval BIJI' : 'Survey BIJI',
                'survey_status' => $user->iot_assigned ? 'Menunggu Approval Remote' : 'Menunggu Jadwal Survey',
                'status' => $batch->status,
            ],
            'survey' => [
                'submitted_at' => $batch->updated_at->toIso8601String(),
                'estimated_approval_window' => '1-2 hari kerja',
                'next_step' => 'Tunggu hasil review dokumen oleh tim BIJI',
                'is_first_survey' => ! $user->iot_assigned,
                'iot_installation_included' => ! $user->iot_assigned,
                'estimated_survey_window' => '1-2 hari kerja',
            ],
        ];

        if ($user->iot_assigned) {
            $data['survey']['existing_iot_sensor_id'] = $user->iot_sensor_id;
        }

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => $user->iot_assigned ? 'Pengajuan approval BIJI berhasil dikirim' : 'Pengajuan survey BIJI berhasil dikirim',
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where('batch_id', $id)->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Batch tidak ditemukan',
            ], 404);
        }

        if ($batch->farmer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'code' => 'BATCH_NOT_OWNED',
                'message' => 'Akses ditolak',
            ], 403);
        }

        if (! in_array($batch->status, ['survey_pending', 'remote_review'])) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
                'message' => 'Hanya survey pending atau remote review yang dapat dibatalkan',
            ], 400);
        }

        $batch->status = 'draft';
        $batch->save();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Pengajuan survey berhasil dibatalkan',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
