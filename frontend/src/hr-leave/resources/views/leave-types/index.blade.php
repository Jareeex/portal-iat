@extends(config('hr.layout'))

@section(config('hr.section'))
@php $r = config('hr.route.name'); @endphp
<div class="hr-wrap">
    <h1>Jenis Cuti</h1>
    <p><a href="{{ route($r.'leave-requests.index') }}">&larr; Inbox pengajuan</a></p>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    @if ($errors->any()) <ul>@foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach</ul> @endif

    <p><small>Kolom: Nama | Kode | Jatah per tahun (kosong = tanpa batas) | Wajib lampiran | Berbayar | Aktif</small></p>

    @foreach ($types as $t)
        <form method="POST" action="{{ route($r.'leave-types.update', $t) }}" style="display:inline">
            @csrf @method('PUT')
            <input name="name" value="{{ $t->name }}" required>
            <input name="code" value="{{ $t->code }}" size="10" required>
            <input type="number" name="annual_quota_days" value="{{ $t->annual_quota_days }}" min="0" placeholder="tanpa batas">
            <label><input type="checkbox" name="requires_attachment" value="1" @checked($t->requires_attachment)> lampiran</label>
            <label><input type="checkbox" name="is_paid" value="1" @checked($t->is_paid)> berbayar</label>
            <label><input type="checkbox" name="is_active" value="1" @checked($t->is_active)> aktif</label>
            <button type="submit">Simpan</button>
        </form>
        <form method="POST" action="{{ route($r.'leave-types.destroy', $t) }}" style="display:inline" onsubmit="return confirm('Hapus?')">
            @csrf @method('DELETE') <button type="submit">Hapus</button>
        </form>
        <hr>
    @endforeach

    <h3>Tambah jenis cuti</h3>
    <form method="POST" action="{{ route($r.'leave-types.store') }}">
        @csrf
        <input name="name" placeholder="Nama baru" required>
        <input name="code" placeholder="KODE" size="10" required>
        <input type="number" name="annual_quota_days" min="0" placeholder="tanpa batas">
        <label><input type="checkbox" name="requires_attachment" value="1"> lampiran</label>
        <label><input type="checkbox" name="is_paid" value="1" checked> berbayar</label>
        <label><input type="checkbox" name="is_active" value="1" checked> aktif</label>
        <button type="submit">Tambah</button>
    </form>
</div>
@endsection
