<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        // Default values if not exist
        $defaults = [
            'check_in_start' => '06:00',
            'check_in_limit' => '08:00',
            'check_out_start' => '16:00',
            'is_wfh' => '0',
            'office_lat' => '-8.7183',
            'office_lng' => '121.1278',
            'office_radius' => '50',
        ];

        return response()->json([
            'status' => 'success',
            'data' => array_merge($defaults, $settings)
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $isAuthorized = false;
        
        if ($user && $user->relationLoaded('roles')) {
            foreach ($user->roles as $role) {
                if ($role->name === 'Super Admin') {
                    $isAuthorized = true;
                    break;
                }
                if (isset($role->permissions)) {
                    foreach ($role->permissions as $perm) {
                        if ($perm->name === 'manage-presensi') {
                            $isAuthorized = true;
                            break 2;
                        }
                    }
                }
            }
        }

        if (!$isAuthorized) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'check_in_start' => 'required|date_format:H:i',
            'check_in_limit' => 'required|date_format:H:i',
            'check_out_start' => 'required|date_format:H:i',
            'is_wfh' => 'required|boolean',
            'office_lat' => 'required|numeric',
            'office_lng' => 'required|numeric',
            'office_radius' => 'required|numeric|min:10',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengaturan jam presensi berhasil disimpan.'
        ]);
    }
}
