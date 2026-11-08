@extends(config('hr.layout'))

@section(config('hr.section'))
@php $r = config('hr.route.name'); @endphp
<div class="hr-wrap">
    <h1>Employees</h1>

    @if (session('status')) <p>{{ session('status') }}</p> @endif

    <form method="GET" style="margin-bottom:1rem">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / no. pegawai / jabatan">
        <select name="department">
            <option value="">Semua departemen</option>
            @foreach ($departments as $d)
                <option value="{{ $d }}" @selected(request('department') === $d)>{{ $d }}</option>
            @endforeach
        </select>
        <select name="employment_status">
            <option value="">Semua status</option>
            @foreach (config('hr.options.employment_statuses') as $s)
                <option value="{{ $s }}" @selected(request('employment_status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button type="submit">Filter</button>
        @can('create', \App\Modules\Hr\Models\Employee::class)
            <a href="{{ route($r.'employees.create') }}">+ Employee baru</a>
        @endcan
    </form>

    <table border="1" cellpadding="6" cellspacing="0" width="100%">
        <thead>
            <tr><th>No. Pegawai</th><th>Nama</th><th>Email</th><th>Jabatan</th><th>Departemen</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($employees as $e)
            <tr>
                <td>{{ $e->employee_number ?? '-' }}</td>
                <td>{{ $e->user?->name }}</td>
                <td>{{ $e->user?->email }}</td>
                <td>{{ $e->job_title ?? '-' }}</td>
                <td>{{ $e->department ?? '-' }}</td>
                <td>{{ $e->employment_status ?? '-' }}</td>
                <td>
                    <a href="{{ route($r.'employees.show', $e) }}">Detail</a> |
                    <a href="{{ route($r.'employees.edit', $e) }}">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7">Belum ada data.</td></tr>
        @endforelse
        </tbody>
    </table>

    {{ $employees->links() }}
</div>
@endsection
