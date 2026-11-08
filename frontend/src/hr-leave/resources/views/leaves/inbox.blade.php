@extends(config('hr.layout'))

@section(config('hr.section'))
@php
    $r = config('hr.route.name');
    $badge = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];
@endphp
<div class="hr-wrap">
    <h1>Pengajuan Cuti Masuk</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif

    <p>{{ $pendingCount }} pengajuan menunggu keputusan &middot;
        <a href="{{ route($r.'leave-types.index') }}">Kelola jenis cuti</a></p>

    <form method="GET" style="margin-bottom:1rem">
        <select name="status">
            <option value="all" @selected($currentStatus === 'all')>Semua status</option>
            @foreach ($badge as $k => $v)
                <option value="{{ $k }}" @selected($currentStatus === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <input name="q" value="{{ request('q') }}" placeholder="Nama / no. pegawai">
        <button type="submit">Filter</button>
    </form>

    <table border="1" cellpadding="6" cellspacing="0" width="100%">
        <thead><tr><th>Diajukan</th><th>Karyawan</th><th>Departemen</th><th>Jenis</th><th>Periode</th><th>Hari</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($leaves as $l)
            <tr>
                <td>{{ $l->created_at->format('d M Y') }}</td>
                <td>{{ $l->employee?->user?->name }} <small>({{ $l->employee?->employee_number ?? '-' }})</small></td>
                <td>{{ $l->employee?->department ?? '-' }}</td>
                <td>{{ $l->leaveType->name }}</td>
                <td>{{ $l->start_date->format('d M') }} &ndash; {{ $l->end_date->format('d M Y') }}</td>
                <td>{{ $l->days }}</td>
                <td>{{ $badge[$l->status] ?? $l->status }}</td>
                <td><a href="{{ route($r.'leaves.show', $l) }}">{{ $l->isPending() ? 'Review' : 'Detail' }}</a></td>
            </tr>
        @empty
            <tr><td colspan="8">Tidak ada pengajuan.</td></tr>
        @endforelse
        </tbody>
    </table>

    {{ $leaves->links() }}
</div>
@endsection
