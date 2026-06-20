<?php

namespace App\Helpers;

use Carbon\Carbon;

class ApiResponse 
{
    public static function format($success, $code, $message, $data = [], $details = null) 
    {
        $response = [
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];

        if ($success) {
            $response['data'] = $data;
        } else {
            $response['details'] = $details;
        }

        return $response;
    }
}