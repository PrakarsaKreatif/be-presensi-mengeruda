<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Exception;

class JwtVerifier
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token not provided.'], 401);
        }

        try {
            $secret = env('JWT_SECRET');
            
            if (!$secret) {
                throw new Exception('JWT_SECRET is not configured in .env');
            }

            // Decode the token using HS256 algorithm
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            // Create a virtual user object from the JWT payload
            $jwtUser = new User();
            $jwtUser->id = $decoded->sub;
            $jwtUser->name = $decoded->name ?? null;
            $jwtUser->nik = $decoded->nik ?? null;
            $jwtUser->phone = $decoded->phone ?? null;
            $jwtUser->email = $decoded->email ?? null;
            $jwtUser->is_approved = (bool)($decoded->is_approved ?? false);

            // Upsert (Sync) user to local database for JOINs
            $localUser = User::find($jwtUser->id);
            if (!$localUser) {
                $conflict = User::where('email', $jwtUser->email)->first();
                if ($conflict) {
                    $conflict->delete();
                }
            }

            $localUser = User::updateOrCreate(
                ['id' => $jwtUser->id],
                [
                    'name' => $jwtUser->name,
                    'email' => $jwtUser->email,
                    'nik' => $jwtUser->nik,
                    'phone' => $jwtUser->phone,
                    'is_approved' => $jwtUser->is_approved,
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)) // Dummy password
                ]
            );

            // Sync Roles to local database
            if (isset($decoded->roles)) {
                $roleIds = [];
                foreach ($decoded->roles as $roleData) {
                    $role = \App\Models\Role::firstOrCreate(['name' => $roleData->name]);
                    $roleIds[] = $role->id;
                }
                $localUser->roles()->sync($roleIds);

                // Re-build virtual relations for the request if needed
                $roles = collect($decoded->roles)->map(function ($role) {
                    $roleObj = new \stdClass();
                    $roleObj->name = $role->name;
                    $roleObj->permissions = collect($role->permissions)->map(function ($perm) {
                        $permObj = new \stdClass();
                        $permObj->name = $perm;
                        return $permObj;
                    });
                    return $roleObj;
                });
                $localUser->setRelation('roles', $roles);
            }

            if (isset($decoded->applications)) {
                $localUser->applications = $decoded->applications;
            }

            // Authenticate the virtual user for this request
            Auth::setUser($localUser);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('JWT Verification Failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Invalid token or server error.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 401);
        }

        return $next($request);
    }
}
