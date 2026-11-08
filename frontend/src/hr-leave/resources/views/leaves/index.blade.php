@extends(config('hr.layout'))

@section(config('hr.section'))
@php
    $r = config('hr.route.name');
    $badge = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];
@endphp
<div class="hr-wrap">
    <h1>Cuti Saya</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif

    <h3>Sisa jatah {{ now()->year }}</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>Jenis</th><th>Terpakai/Booking</th><th>Sisa</th></tr>
        @foreach ($balances as $b)
            <tr>
                <td>{{ $b['type']->name }}</td>
                <td>{{ $b['used'] }} hari</td>
                <td>{{ $b['remaining'] === null ? 'Tanpa batas' : $b['remaining'].' hari' }}</td>
            </tr>
        @endforeach
    </table>

    <p>
        <a href="{{ route($r.'leaves.create') }}">+ Ajukan cuti</a>
        &middot; Filter:
        <a href="{{ route($r.'leaves.index') }}">Semua</a>
        @foreach ($badge as $k => $v) | <a href="{{ route($r.'leaves.index', ['status' => $k]) }}">{{ $v }}</a> @endforeach
    </p>

    <table border="1" cellpadding="6" cellspacing="0" width="100%">
        <thead><tr><th>Diajukan</th><th>Jenis</th><th>Periode</th><th>Hari</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($leaves as $l)
            <tr>
                <td>{{ $l->created_at->format('d M Y') }}</td>
                <td>{{ $l->leaveType->name }}</td>
                <td>{{ $l->start_date->format('d M Y') }} &ndash; {{ $l->end_date->format('d M Y') }}</td>
                <td>{{ $l->days }}</td>
                <td>{{ $badge[$l->status] ?? $l->status }}</td>
                <td><a href="{{ route($r.'leaves.show', $l) }}">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="6">Belum ada pengajuan.</td></tr>
        @endforelse
        </tbody>
    </table>

    {{ $leaves->links() }}
</div>
@endsection
