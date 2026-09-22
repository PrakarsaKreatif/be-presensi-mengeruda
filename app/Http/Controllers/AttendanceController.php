<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    public function today(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $now = \Carbon\Carbon::now();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', $now->toDateString())
            ->first();

        // Ambil pengaturan jam untuk frontend
        $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
        $defaults = [
            'check_in_start' => '06:00',
            'check_in_limit' => '08:00',
            'check_out_start' => '16:00',
        ];

        return response()->json([
            'status' => 'success',
            'data' => $attendance,
            'settings' => array_merge($defaults, $settings)
        ]);
    }

    public function history(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Attendance::where('user_id', $user->id)
                           ->orderBy('check_in_time', 'desc');

        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('check_in_time', $request->month)
                  ->whereYear('check_in_time', $request->year);
        }

        $attendances = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $attendances
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userLat = $request->latitude;
        $userLng = $request->longitude;

        $now = \Carbon\Carbon::now(); // Uses APP_TIMEZONE="Asia/Makassar"
        
        // Ambil pengaturan jam dan lokasi
        $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
        $checkInStart = $settings['check_in_start'] ?? '06:00';
        $checkInLimit = $settings['check_in_limit'] ?? '08:00';
        $checkOutStart = $settings['check_out_start'] ?? '16:00';
        $isWfh = filter_var($settings['is_wfh'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $officeLat = floatval($settings['office_lat'] ?? -8.7183);
        $officeLng = floatval($settings['office_lng'] ?? 121.1278);
        $officeRadius = floatval($settings['office_radius'] ?? 50);

        // Haversine formula
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($userLat - $officeLat);
        $dLng = deg2rad($userLng - $officeLng);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($officeLat)) * cos(deg2rad($userLat)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        // Parsing waktu menggunakan timezone yang sudah terikat di $now
        $timeInStart = $now->copy()->setTimeFromTimeString($checkInStart);
        $timeInLimit = $now->copy()->setTimeFromTimeString($checkInLimit);
        $timeOutStart = $now->copy()->setTimeFromTimeString($checkOutStart);

        // Handle photo upload
        $photoPath = $request->file('photo')->store('presensi', 'public');

        // Check for existing attendance today
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', $now->toDateString())
            ->first();

        // Validasi jarak jika tidak sedang mode WFH
        if (!$isWfh && $distance > $officeRadius) {
            Storage::disk('public')->delete($photoPath);
            return response()->json([
                'status' => 'error',
                'message' => 'Anda berada di luar jangkauan area presensi (' . round($distance) . ' meter)',
            ], 403);
        }

        if (!$attendance) {
            // Absen Masuk
            if ($now->lessThan($timeInStart)) {
                Storage::disk('public')->delete($photoPath);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Belum waktunya absen masuk. Absen dibuka jam ' . $checkInStart,
                ], 400);
            }

            $status = 'Tepat Waktu';
            $lateDuration = null;

            if ($now->greaterThan($timeInLimit)) {
                $status = 'Terlambat';
                $lateDuration = $now->diffInMinutes($timeInLimit);
            }

            $attendance = Attendance::create([
                'user_id' => $user->id,
                'latitude' => $userLat,
                'longitude' => $userLng,
                'status' => $status,
                'late_duration' => $lateDuration,
                'check_in_time' => $now,
                'photo_in_path' => $photoPath,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Presensi masuk berhasil dicatat: ' . $status,
                'data' => $attendance
            ]);
        } else {
            // Absen Pulang
            if ($attendance->check_out_time) {
                Storage::disk('public')->delete($photoPath);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda sudah melakukan presensi masuk dan pulang hari ini.'
                ], 400);
            }

            if ($now->lessThan($timeOutStart)) {
                Storage::disk('public')->delete($photoPath);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Belum waktunya absen pulang. Pulang dibuka jam ' . $checkOutStart,
                ], 400);
            }

            $attendance->update([
                'check_out_time' => $now,
                'photo_out_path' => $photoPath,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Presensi pulang berhasil dicatat.',
                'data' => $attendance
            ]);
        }
    }
}
