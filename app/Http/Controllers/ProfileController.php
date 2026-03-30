<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Log;


class ProfileController extends Controller
{
    public function profile(): JsonResponse
    {
        try{
            $user = Auth::user();

            // add plan info
            $plan = $user->subscription()->where('status', 'active')->first()->plan ?? 'Free';
            $user->plan = $plan;
            return response()->json(['user' => $user], 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'Something went wrong, please try again later.'], 500);
        }
    }

    public function updateProfile(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
            ],[
                'name.required' => 'The name field is required.',
                'name.string' => 'The name must be a string.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $user->update([
                'name' => $request->name,
            ]);
            
            return response()->json(['message' => 'Profile updated successfully.', 'user' => $user], 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to update profile, please try again later.'], 500);
        }
    }

    public function checkPlan(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $plan = $user->subscription()->where('status', 'active')->first()->plan ?? 'Free';
            return response()->json(['plan' => $plan], 200);
        }catch(Exception $e){
            return response()->json(['error', $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $user->is_deleted = true;
            $user->save();
            $user->tokens()->delete();
            return response()->json(['message' => 'Account deleted successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Validation
            $validator = Validator::make($request->all(), [
                'old_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ], [
                'old_password.required' => 'Old password is required.',
                'new_password.required' => 'New password is required.',
                'new_password.min' => 'New password must be at least 6 characters.',
                'new_password.confirmed' => 'New password confirmation does not match.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Check old password
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'error' => 'Old password is incorrect.'
                ], 400);
            }

            // Prevent same password reuse (optional but best practice)
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'error' => 'New password cannot be same as old password.'
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'message' => 'Password updated successfully.'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to update password, please try again later.'
            ], 500);
        }
    }
}
