<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Attendance</title>
    <style>
        @page {
            margin: 24px 28px 36px;
        }

        body {
            color: #172033;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }

        .heading {
            border-bottom: 2px solid #174b84;
            margin-bottom: 16px;
            padding-bottom: 10px;
        }

        .heading h1 {
            color: #173b63;
            font-size: 18px;
            margin: 0 0 5px;
        }

        .heading p,
        .muted {
            color: #64748b;
            margin: 0;
        }

        .summary {
            margin: 0 0 14px;
        }

        .employee {
            margin: 0 0 18px;
            page-break-inside: avoid;
        }

        .employee h2 {
            background: #eaf1f8;
            color: #173b63;
            font-size: 12px;
            margin: 0;
            padding: 7px;
        }

        .employee-meta {
            color: #64748b;
            margin: 0;
            padding: 5px 7px;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            overflow-wrap: break-word;
            padding: 5px 6px;
            vertical-align: top;
        }

        th {
            background: #173b63;
            color: #fff;
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background: #f5f8fc;
        }

        .empty {
            color: #64748b;
            padding: 16px;
            text-align: center;
        }

        .footer {
            bottom: -22px;
            color: #64748b;
            font-size: 8px;
            position: fixed;
            right: 0;
        }
    </style>
</head>
<body>
    <header class="heading">
        <h1>LAPORAN ATTENDANCE</h1>
        <p>Dicetak {{ $generatedAt->format('d/m/Y H:i') }}</p>
    </header>

    <p class="summary">
        Periode <strong>{{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }}</strong>
        s.d. <strong>{{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}</strong>
        · Karyawan: <strong>{{ $name ?: 'Semua karyawan' }}</strong>
        · Total catatan: <strong>{{ $employeeGroups->flatten()->count() }}</strong>
    </p>

    @forelse ($employeeGroups as $employeeRecords)
        @php($employee = $employeeRecords->first()->employee)
        <section class="employee">
            <h2>{{ $employee->user?->name ?? $employee->name ?? 'Karyawan' }}</h2>
            <p class="employee-meta">
                {{ $employee->employee_number ?: 'No. pegawai belum diisi' }}
                @if ($employee->user?->department?->name)
                    · {{ $employee->user->department->name }}
                @endif
            </p>
            <table>
                <colgroup>
                    <col style="width: 12%">
                    <col style="width: 11%">
                    <col style="width: 27%">
                    <col style="width: 11%">
                    <col style="width: 27%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Clock-in</th>
                        <th>Detail clock-in</th>
                        <th>Clock-out</th>
                        <th>Detail clock-out</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employeeRecords as $attendance)
                        <tr>
                            <td>{{ $attendance->attendance_date->format('d/m/Y') }}</td>
                            <td>{{ $attendance->clock_in_at->copy()->setTimezone($timezone)->format('H:i') }}</td>
                            <td>
                                @if ($attendance->clock_in_is_offsite)
                                    On Leave
                                    @if ($attendance->clock_in_reason)
                                        · {{ $offsiteReasons[$attendance->clock_in_reason] ?? $attendance->clock_in_reason }}
                                    @endif
                                    @if ($attendance->clock_in_reason_details)
                                        · {{ $attendance->clock_in_reason_details }}
                                    @endif
                                @else
                                    {{ $attendance->clock_in_distance_meters === null
                                        ? 'Lokasi tidak tercatat'
                                        : $attendance->clock_in_distance_meters.' m dari lokasi kerja' }}
                                @endif
                            </td>
                            <td>
                                {{ $attendance->clock_out_at
                                    ? $attendance->clock_out_at->copy()->setTimezone($timezone)->format('H:i')
                                    : 'Belum clock-out' }}
                            </td>
                            <td>
                                @if ($attendance->clock_out_at)
                                    @if ($attendance->clock_out_is_offsite)
                                        On Leave
                                        @if ($attendance->clock_out_reason)
                                            · {{ $offsiteReasons[$attendance->clock_out_reason] ?? $attendance->clock_out_reason }}
                                        @endif
                                        @if ($attendance->clock_out_reason_details)
                                            · {{ $attendance->clock_out_reason_details }}
                                        @endif
                                    @else
                                        {{ $attendance->clock_out_distance_meters === null
                                            ? 'Lokasi tidak tercatat'
                                            : $attendance->clock_out_distance_meters.' m dari lokasi kerja' }}
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="empty">Tidak ada data attendance pada filter tersebut.</p>
    @endforelse

    <div class="footer">Laporan attendance · {{ $generatedAt->format('d/m/Y H:i') }}</div>
</body>
</html>
