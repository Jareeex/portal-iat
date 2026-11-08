@extends(config('hr.layout'))

@section(config('hr.section'))
@php
    $r = config('hr.route.name');
    $badge = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];
    $back = Gate::allows('hr.manage') && $leave->employee?->user_id !== auth()->id()
        ? route($r.'leave-requests.index') : route($r.'leaves.index');
    $rows = [
        'Karyawan'      => $leave->employee?->user?->name.' ('.($leave->employee?->employee_number ?? '-').')',
        'Departemen'    => $leave->employee?->department ?? '-',
        'Jenis cuti'    => $leave->leaveType->name.($leave->leaveType->is_paid ? '' : ' (tanpa gaji)'),
        'Periode'       => $leave->start_date->format('d M Y').' - '.$leave->end_date->format('d M Y'),
        'Jumlah hari'   => $leave->days.' hari kerja',
        'Alasan'        => $leave->reason,
        'Kontak'        => $leave->contact_phone ?? '-',
        'Status'        => $badge[$leave->status] ?? $leave->status,
        'Diajukan'      => $leave->created_at->format('d M Y H:i'),
    ];
    if ($leave->reviewed_at) {
        $rows['Diputuskan oleh'] = $leave->reviewer?->name ?? '-';
        $rows['Waktu keputusan'] = $leave->reviewed_at->format('d M Y H:i');
        $rows['Catatan HR']      = $leave->review_note ?? '-';
    }
@endphp
<div class="hr-wrap">
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    @if ($errors->any()) <ul>@foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach</ul> @endif

    <h1>Pengajuan Cuti #{{ $leave->id }}</h1>
    <p><a href="{{ $back }}">&larr; Kembali</a></p>

    <table border="1" cellpadding="6" cellspacing="0" width="100%">
        @foreach ($rows as $label => $value)
            <tr><th align="left" width="30%">{{ $label }}</th><td>{{ $value }}</td></tr>
        @endforeach
        @if ($leave->attachment_path)
            <tr><th align="left">Lampiran</th><td><a href="{{ route($r.'leaves.attachment', $leave) }}">Download</a></td></tr>
        @endif
    </table>

    @can('cancel', $leave)
        <form method="POST" action="{{ route($r.'leaves.cancel', $leave) }}" onsubmit="return confirm('Batalkan pengajuan?')">
            @csrf <button type="submit">Batalkan pengajuan</button>
        </form>
    @endcan

    @can('review', $leave)
        <h3>Keputusan HR</h3>
        <form method="POST" action="{{ route($r.'leaves.approve', $leave) }}" style="display:inline-block;margin-right:2rem">
            @csrf
            <input name="review_note" placeholder="Catatan (opsional)">
            <button type="submit">Setujui</button>
        </form>
        <form method="POST" action="{{ route($r.'leaves.reject', $leave) }}" style="display:inline-block">
            @csrf
            <input name="review_note" placeholder="Alasan penolakan (wajib)" required>
            <button type="submit">Tolak</button>
        </form>
    @endcan
</div>
@endsection
