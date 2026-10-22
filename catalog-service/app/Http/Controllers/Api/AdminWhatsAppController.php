<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppOpsClient;
use Illuminate\Http\Request;

class AdminWhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppOpsClient $client)
    {
    }

    public function sessions(Request $request)
    {
        return response()->json($this->client->sessions($request->query()));
    }

    public function assign(Request $request, string $waId)
    {
        $validated = $request->validate([
            'assignee_id' => 'required|uuid',
        ]);

        return response()->json($this->client->assign($waId, $validated['assignee_id']));
    }
}
