<?php

namespace App\Http\Controllers;

use App\Models\DomainRotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

class WebhookController extends Controller
{
    public function mailboxes(Request $request): JsonResponse
    {
        $data = DomainRotation::select('master_email as user', 'master_password as password')->get();

        return response()->json($data);
    }

    public function webhook(Request $request): JsonResponse
    {
        // Handle incoming webhook data
        Log::info('Webhook received:', $request->all());
        return response()->json(['status' => 'success']);
    }
}
