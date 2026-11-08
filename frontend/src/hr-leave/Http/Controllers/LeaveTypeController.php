<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Http\Requests\LeaveTypeRequest;
use App\Modules\Hr\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class LeaveTypeController extends Controller
{
    /** HR: halaman kelola. Karyawan: hanya JSON jenis cuti aktif (buat form di frontend lain). */
    public function index(Request $request)
    {
        $manage = Gate::allows('hr.manage');

        $types = LeaveType::when(! $manage, fn ($q) => $q->active())->orderBy('name')->get();

        if ($request->wantsJson()) {
            return response()->json($types);
        }

        abort_unless($manage, 403);

        return view('hr::leave-types.index', compact('types'));
    }

    public function store(LeaveTypeRequest $request)
    {
        $type = LeaveType::create($request->validated());

        return $request->wantsJson()
            ? response()->json($type, 201)
            : back()->with('status', 'Jenis cuti ditambahkan.');
    }

    public function update(LeaveTypeRequest $request, LeaveType $leaveType)
    {
        $leaveType->update($request->validated());

        return $request->wantsJson()
            ? response()->json($leaveType)
            : back()->with('status', 'Jenis cuti diupdate.');
    }

    /** Kalau sudah dipakai pengajuan, cukup dinonaktifkan supaya riwayat tetap utuh. */
    public function destroy(Request $request, LeaveType $leaveType)
    {
        Gate::authorize('hr.manage');

        if ($leaveType->requests()->exists()) {
            $leaveType->update(['is_active' => false]);
            $message = 'Jenis cuti sudah dipakai, jadi dinonaktifkan (tidak dihapus).';
        } else {
            $leaveType->delete();
            $message = 'Jenis cuti dihapus.';
        }

        return $request->wantsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }
}
