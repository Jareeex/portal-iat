@extends(config('hr.layout'))

@section(config('hr.section'))
<div class="hr-wrap">
    <h1>Form Pengajuan Cuti</h1>

    @if ($errors->any())
        <ul>@foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach</ul>
    @endif

    <form method="POST" action="{{ route(config('hr.route.name').'leaves.store') }}" enctype="multipart/form-data">
        @csrf

        <p><label>Jenis cuti<br>
            <select name="leave_type_id" required>
                <option value="">-- pilih --</option>
                @foreach ($balances as $b)
                    <option value="{{ $b['type']->id }}" @selected((string) old('leave_type_id') === (string) $b['type']->id)>
                        {{ $b['type']->name }}
                        ({{ $b['remaining'] === null ? 'tanpa batas' : 'sisa '.$b['remaining'].' hari' }}{{ $b['type']->requires_attachment ? ', wajib lampiran' : '' }})
                    </option>
                @endforeach
            </select></label></p>

        <p><label>Tanggal mulai<br><input type="date" name="start_date" value="{{ old('start_date') }}" required></label></p>
        <p><label>Tanggal selesai<br><input type="date" name="end_date" value="{{ old('end_date') }}" required></label></p>
        <p><small>Sabtu/Minggu dan hari libur tidak dihitung. Jumlah hari dihitung otomatis oleh sistem.</small></p>

        <p><label>Alasan<br><textarea name="reason" rows="4" cols="50" required>{{ old('reason') }}</textarea></label></p>
        <p><label>No. kontak selama cuti<br><input name="contact_phone" value="{{ old('contact_phone') }}"></label></p>
        <p><label>Lampiran (PDF/JPG/PNG, maks 4MB)<br><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label></p>

        <button type="submit">Kirim ke HR</button>
        <a href="{{ route(config('hr.route.name').'leaves.index') }}">Batal</a>
    </form>
</div>
@endsection
