@extends(config('hr.layout'))

@section(config('hr.section'))
@php
    $o = config('hr.options');
    $admin = $mode === 'admin';
    $creating = ! $employee->exists;
    $val = function ($k) use ($employee) {
        $v = $employee->{$k};
        return old($k, $v instanceof \Carbon\CarbonInterface ? $v->toDateString() : $v);
    };

    // [name, label, type, options]
    $employment = [
        ['employee_number', 'No. Pegawai', 'text'], ['job_title', 'Jabatan (Function)', 'text'],
        ['department', 'Departemen', 'text'], ['office', 'Kantor', 'text'],
        ['employment_status', 'Status', 'select', $o['employment_statuses']],
        ['join_date', 'Tanggal Masuk', 'date'], ['end_date', 'Tanggal Keluar', 'date'],
    ];
    $sections = [
        'Personal' => [
            ['place_of_birth', 'Tempat Lahir', 'text'], ['date_of_birth', 'Tanggal Lahir', 'date'],
            ['gender', 'Jenis Kelamin', 'select', $o['genders']], ['marital_status', 'Status Pernikahan', 'select', $o['marital_statuses']],
            ['religion', 'Agama', 'select', $o['religions']], ['address', 'Alamat', 'textarea'], ['phone', 'Phone/HP', 'text'],
        ],
        'Identitas' => [
            ['ktp_number', 'No. KTP (16 digit)', 'text'], ['ktp_issued_at', 'KTP Diterbitkan', 'date'],
            ['ktp_expired_at', 'KTP Berlaku s/d', 'date'], ['ktp_place', 'KTP Tempat', 'text'],
            ['passport_number', 'No. Paspor', 'text'], ['passport_issued_at', 'Paspor Diterbitkan', 'date'],
            ['passport_expired_at', 'Paspor Berlaku s/d', 'date'], ['passport_place', 'Paspor Tempat', 'text'],
            ['npwp_number', 'No. NPWP', 'text'],
        ],
        'Fisik' => [
            ['weight_kg', 'Berat (kg)', 'number'], ['height_cm', 'Tinggi (cm)', 'number'],
            ['blood_type', 'Golongan Darah', 'select', $o['blood_types']],
        ],
        'Pendidikan Terakhir' => [
            ['highest_education', 'Jenjang', 'select', $o['educations']], ['education_institution', 'Institusi', 'text'],
            ['education_major', 'Jurusan', 'text'], ['graduated_year', 'Tahun Lulus', 'number'],
        ],
    ];
    if ($admin) { $sections = ['Kepegawaian' => $employment] + $sections; }

    $render = function ($f) use ($val) {
        [$name, $label, $type] = $f;
        $opts = $f[3] ?? [];
        $v = $val($name);
        $html = '<p><label>' . e($label) . '<br>';
        if ($type === 'select') {
            $html .= '<select name="' . $name . '"><option value="">-</option>';
            foreach ($opts as $op) {
                $html .= '<option value="' . e($op) . '"' . ($v === $op ? ' selected' : '') . '>' . e($op) . '</option>';
            }
            $html .= '</select>';
        } elseif ($type === 'textarea') {
            $html .= '<textarea name="' . $name . '" rows="3" cols="40">' . e($v) . '</textarea>';
        } else {
            $step = $type === 'number' ? ' step="any"' : '';
            $html .= '<input type="' . $type . '" name="' . $name . '" value="' . e($v) . '"' . $step . '>';
        }
        return $html . '</label></p>';
    };
@endphp
<div class="hr-wrap">
    <h1>{{ $creating ? 'Employee baru' : ($admin ? 'Edit employee' : 'Edit profil saya') }}</h1>

    @if ($errors->any())
        <ul>@foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach</ul>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
        @csrf
        @if ($method !== 'POST') @method($method) @endif

        @if ($admin)
            <h3>Akun User</h3>
            <p><label>Nama<br><input name="name" value="{{ old('name', $employee->user?->name) }}" required></label></p>
            <p><label>Email<br><input type="email" name="email" value="{{ old('email', $employee->user?->email) }}" required></label></p>
            <p><label>Password {{ $creating ? '' : '(kosongkan jika tidak diganti)' }}<br>
                <input type="password" name="password" {{ $creating ? 'required' : '' }}></label></p>
        @endif

        <p><label>Foto<br><input type="file" name="photo" accept="image/*"></label></p>

        @foreach ($sections as $title => $fields)
            <h3>{{ $title }}</h3>
            @foreach ($fields as $f) {!! $render($f) !!} @endforeach
        @endforeach

        <button type="submit">Simpan</button>
    </form>
</div>
@endsection
