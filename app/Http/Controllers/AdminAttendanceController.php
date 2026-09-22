<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Cek Hak Akses (Role: Super Admin ATAU Permission: manage-presensi)
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
            return response()->json(['message' => 'Anda tidak memiliki hak akses untuk fitur ini.'], 403);
        }

        // Ambil data berdasarkan filter tanggal (default hari ini jika tidak ada query 'date')
        $date = $request->query('date');
        
        $query = Attendance::with('user:id,name,nik');

        if ($date && $date !== 'all') {
            $query->whereDate('check_in_time', $date);
        }

        $attendances = $query->orderBy('check_in_time', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $attendances
        ]);
    }
}
