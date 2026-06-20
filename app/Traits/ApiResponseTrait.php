<?php

namespace App\Traits;

use Carbon\Carbon;

/**
 * ApiResponseTrait - Trait untuk response API seragam
 * 
 * Sesuai dengan API_CONTRACT.md Section 4:
 * - Format response sukses: {success, code, message, data, timestamp}
 * - Format response error: {success, code, message, details, timestamp}
 * - Format response berpagginasi: {success, code, data, pagination, timestamp}
 * 
 * Usage:
 * class MyController extends Controller {
 *     use ApiResponseTrait;
 *     
 *     public function index() {
 *         return $this->apiResponse(true, 'SUCCESS', 'Data retrieved', $data);
 *     }
 * }
 */
trait ApiResponseTrait
{
    /**
     * Generate standardized API response
     * 
     * @param bool $success Whether the request was successful
     * @param string $code Response code (SUCCESS, SUCCESS_CREATE, ERROR_CODE, etc.)
     * @param string $message Human-readable message
     * @param array|object $data Response payload (default: empty array)
     * @param int $status HTTP status code (default: 200)
     * @param array|object|null $details Additional details for error responses
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    protected function apiResponse(
        bool $success,
        string $code,
        string $message,
        $data = [],
        int $status = 200,
        $details = null
    ) {
        $response = [
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];

        // Jika ada details (untuk error response), tambahkan ke response
        if ($details !== null) {
            $response['details'] = $details;
        }

        return response()->json($response, $status);
    }

    /**
     * Generate success response with pagination
     * 
     * @param array $data Array of items
     * @param string $code Response code (default: SUCCESS)
     * @param string $message Human-readable message
     * @param string|null $cursor Base64-encoded cursor for next page
     * @param bool $hasMore Whether more data is available
     * @param int $limit Items per page
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    protected function apiResponsePaginated(
        array $data,
        string $code = 'SUCCESS',
        string $message = '',
        ?string $cursor = null,
        bool $hasMore = false,
        int $limit = 20,
        ?int $total = null
    ) {
        $pagination = [
            'cursor' => $cursor,
            'hasMore' => $hasMore,
            'limit' => $limit,
        ];

        if ($total !== null) {
            $pagination['total'] = $total;
        }

        return response()->json([
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination,
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Generate error response
     * 
     * @param string $code Error code (UNAUTHORIZED, FORBIDDEN, NOT_FOUND, etc.)
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array|object|null $details Error details (validation errors, etc.)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    protected function apiErrorResponse(
        string $code,
        string $message,
        int $status = 400,
        $details = null
    ) {
        return $this->apiResponse(false, $code, $message, [], $status, $details);
    }

    /**
     * Generate validation error response
     * 
     * @param array $errors Validation errors keyed by field name
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    protected function apiValidationError(array $errors)
    {
        return $this->apiErrorResponse(
            'VALIDATION_ERROR',
            'Validasi input gagal',
            422,
            $errors
        );
    }
}
