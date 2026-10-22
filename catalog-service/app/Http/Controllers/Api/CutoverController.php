<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cutover\CutoverGate;

class CutoverController extends Controller
{
    public function readiness(CutoverGate $gate)
    {
        return response()->json($gate->cached());
    }
}
