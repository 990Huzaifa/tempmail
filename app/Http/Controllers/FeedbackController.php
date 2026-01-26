<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ],[
            'subject.required' => 'Subject is required',
            'message.required' => 'Message is required',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $feedback = Feedback::create([
            'user_id' => $user->id,
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
        ]);

        return response()->json(['message' => 'Feedback submitted successfully', 'data' => $feedback], 201);
    }
}
