<?php

namespace App\Http\Controllers\Api\v1\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Port;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class BuyerPortController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $ports = Port::where('is_active', true)->get();

        return $this->apiResponse(true, 'SUCCESS', 'Daftar pelabuhan berhasil diambil', $ports);
    }
}
