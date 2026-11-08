@extends(config('hr.layout'))

@section(config('hr.section'))
@php
    $r = config('hr.route.name');
    $self = $mode === 'self';
    $groups = [
        'Kepegawaian' => ['employee_number' => 'No. Pegawai', 'job_title' => 'Jabatan (Function)', 'department' => 'Departemen',
            'office' => 'Kantor', 'employment_status' => 'Status', 'join_date' => 'Tanggal Masuk', 'end_date' => 'Tanggal Keluar'],
        'Personal' => ['place_of_birth' => 'Tempat Lahir', 'date_of_birth' => 'Tanggal Lahir', 'gender' => 'Jenis Kelamin',
            'marital_status' => 'Status Pernikahan', 'religion' => 'Agama', 'address' => 'Alamat', 'phone' => 'Phone/HP'],
        'Identitas' => ['ktp_number' => 'No. KTP', 'ktp_issued_at' => 'KTP Diterbitkan', 'ktp_expired_at' => 'KTP Berlaku s/d',
            'ktp_place' => 'KTP Tempat', 'passport_number' => 'No. Paspor', 'passport_issued_at' => 'Paspor Diterbitkan',
            'passport_expired_at' => 'Paspor Berlaku s/d', 'passport_place' => 'Paspor Tempat', 'npwp_number' => 'No. NPWP'],
        'Fisik' => ['weight_kg' => 'Berat (kg)', 'height_cm' => 'Tinggi (cm)', 'blood_type' => 'Golongan Darah'],
        'Pendidikan' => ['highest_education' => 'Pendidikan Terakhir', 'education_institution' => 'Institusi',
            'education_major' => 'Jurusan', 'graduated_year' => 'Tahun Lulus'],
    ];
    $fmt = fn ($v) => $v instanceof \Carbon\CarbonInterface ? $v->format('d M Y') : (($v === null || $v === '') ? '-' : $v);
    $canEdit = $self || Gate::allows('update', $employee);
@endphp
<div class="hr-wrap">
    @if (session('status')) <p>{{ session('status') }}</p> @endif

    <h1>{{ $employee->user?->name }}</h1>
    <p>{{ $employee->user?->email }} @if ($employee->age) &middot; {{ $employee->age }} th @endif</p>
    @if ($employee->photo_url) <img src="{{ $employee->photo_url }}" alt="Foto" width="120"> @endif

    <p>
        @if ($canEdit)
            <a href="{{ $self ? route($r.'me.edit') : route($r.'employees.edit', $employee) }}">Edit</a>
        @endif
        @unless ($self) | <a href="{{ route($r.'employees.index') }}">Kembali</a> @endunless
    </p>

    @foreach ($groups as $title => $fields)
        <h3>{{ $title }}</h3>
        <table border="1" cellpadding="6" cellspacing="0" width="100%">
            @foreach ($fields as $key => $label)
                <tr><th align="left" width="30%">{{ $label }}</th><td>{{ $fmt($employee->{$key}) }}</td></tr>
            @endforeach
        </table>
    @endforeach

    <h3>Pengalaman Kerja</h3>
    @foreach (['outside' => 'Outside (di luar perusahaan)', 'inside' => 'Inside (riwayat karier internal)'] as $type => $label)
        <h4>{{ $label }}</h4>
        <table border="1" cellpadding="6" cellspacing="0" width="100%">
            <tr><th>Perusahaan</th><th>Posisi</th><th>Mulai</th><th>Selesai</th><th>Keterangan</th><th></th></tr>
            @forelse ($employee->experiences->where('type', $type) as $x)
                <tr>
                    <td>{{ $x->company }}</td><td>{{ $fmt($x->position) }}</td>
                    <td>{{ $fmt($x->start_date) }}</td><td>{{ $fmt($x->end_date) }}</td><td>{{ $fmt($x->description) }}</td>
                    <td>
                        <form method="POST" action="{{ route($r.'employees.experiences.destroy', [$employee, $x]) }}"
                              onsubmit="return confirm('Hapus?')">
                            @csrf @method('DELETE') <button type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">-</td></tr>
            @endforelse
        </table>
    @endforeach

    <h4>Tambah pengalaman</h4>
    <form method="POST" action="{{ route($r.'employees.experiences.store', $employee) }}">
        @csrf
        <select name="type"><option value="outside">Outside</option><option value="inside">Inside</option></select>
        <input name="company" placeholder="Perusahaan" required>
        <input name="position" placeholder="Posisi">
        <input type="date" name="start_date"> <input type="date" name="end_date">
        <input name="description" placeholder="Keterangan">
        <button type="submit">Tambah</button>
        @error('company') <div>{{ $message }}</div> @enderror
        @error('end_date') <div>{{ $message }}</div> @enderror
    </form>
</div>
@endsection
