<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function sendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::success(null, 'Verification link sent successfully.');
    }

    /**
     * Mark the user's email address as verified.
     */
    public function verify(Request $request): JsonResponse
    {
        if ($request->route('id') !== null) {
            $userId = $request->route('id');
            $hash = $request->route('hash');
        } else {
            $request->validate([
                'id' => ['required', 'integer'],
                'hash' => ['required', 'string'],
                'expires' => ['required', 'numeric'],
                'signature' => ['required', 'string'],
            ]);
            $userId = $request->input('id');
            $hash = $request->input('hash');
        }

        $user = User::findOrFail($userId);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return ApiResponse::error('Invalid verification link.', 400, code: 'INVALID_VERIFICATION_LINK');
        }

        if ($request->route('id') !== null) {
            if (! $request->hasValidSignature()) {
                return ApiResponse::error('Invalid or expired verification link.', 400, code: 'INVALID_VERIFICATION_LINK');
            }
        } else {
            $getUrl = URL::route('verification.verify', [
                'id' => $userId,
                'hash' => $hash,
                'expires' => $request->input('expires'),
                'signature' => $request->input('signature'),
            ]);

            $dummyRequest = Request::create($getUrl, 'GET');
            if (! $dummyRequest->hasValidSignature()) {
                return ApiResponse::error('Invalid or expired verification link.', 400, code: 'INVALID_VERIFICATION_LINK');
            }
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email already verified.');
        }

        $user->markEmailAsVerified();

        return ApiResponse::success(null, 'Email verified successfully.');
    }
}
