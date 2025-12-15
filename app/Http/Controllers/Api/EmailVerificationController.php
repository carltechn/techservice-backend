<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Mail\VerifyEmail;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->email))) {
            abort(403, 'Invalid verification link.');
        }

        if (! URL::hasValidSignature($request)) {
            abort(403, 'Verification link has expired or is invalid.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $frontend = env('FRONTEND_URL', config('app.url'));
        return redirect()->away(rtrim($frontend, '/').'/email-verified?status=success');
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Email already verified.'],
            ]);
        }

        $verificationUrl = $this->buildVerificationUrl($user);

        Mail::to($user->email)->send(new VerifyEmail($user, $verificationUrl));

        return response()->json([
            'message' => 'Verification email sent.',
        ]);
    }

    /**
     * Build a signed verification URL using FRONTEND_URL as base.
     */
    private function buildVerificationUrl(User $user): string
    {
        $originalAppUrl = config('app.url');
        $frontendUrl = rtrim(env('FRONTEND_URL', $originalAppUrl), '/');

        Config::set('app.url', $frontendUrl);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
        Config::set('app.url', $originalAppUrl);

        return $url;
    }
}

