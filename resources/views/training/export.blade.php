<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Training Record Crew</title>
    <style>
        @page {
            margin: 24px 28px 36px;
        }

        body {
            color: #172033;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
        }

        .heading {
            border-bottom: 2px solid #174b84;
            margin-bottom: 14px;
            padding-bottom: 9px;
        }

        .heading h1 {
            color: #173b63;
            font-size: 17px;
            margin: 0 0 5px;
        }

        .heading p,
        .summary {
            color: #64748b;
            font-size: 8px;
            margin: 0;
        }

        .summary {
            color: #334155;
            margin-bottom: 9px;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 5px 4px;
            vertical-align: top;
        }

        thead {
            display: table-header-group;
        }

        thead th {
            background: #173b63;
            color: #fff;
            font-size: 7px;
            text-align: left;
        }

        tbody .crew-heading td {
            background: #e8f0f8;
            border-top: 2px solid #174b84;
            color: #173b63;
            font-size: 9px;
            padding: 7px 4px;
        }

        .crew-heading span {
            color: #64748b;
            font-size: 8px;
            font-weight: normal;
            margin-left: 8px;
        }

        tbody tr:nth-child(even) {
            background: #f5f8fc;
        }

        .center {
            text-align: center;
        }

        .empty {
            color: #64748b;
            padding: 16px;
            text-align: center;
        }

        .footer {
            bottom: -22px;
            color: #64748b;
            font-size: 7px;
            position: fixed;
            right: 0;
        }
    </style>
</head>
<body>
    <header class="heading">
        <h1>TRAINING RECORD CREW</h1>
        <p>Dicetak {{ $generatedAt->format('d/m/Y H:i') }}</p>
    </header>

    <p class="summary">
        Total crew: <strong>{{ $crewCount }}</strong>
        &nbsp;·&nbsp;
        Total training: <strong>{{ $trainingCount }}</strong>
        &nbsp;·&nbsp;
        Kelompok: <strong>{{ $groupLabel }}</strong>
    </p>

    <table>
        <colgroup>
            <col style="width: 40%">
            <col style="width: 14%">
            <col style="width: 30%">
            <col style="width: 16%">
        </colgroup>
        <thead>
            <tr>
                <th>BAGIAN / TRAINING</th>
                <th>MASA BERLAKU</th>
                <th>TERAKHIR / BERIKUTNYA / RENCANA</th>
                <th>STATUS</th>
            </tr>
        </thead>
        @forelse ($crewGroups as $crewGroup)
            <tbody>
                <tr class="crew-heading">
                    <td colspan="4">
                        <strong>{{ $crewGroup['crew'] }}</strong>
                        <span>{{ $crewGroup['group'] }}</span>
                    </td>
                </tr>
                @foreach ($crewGroup['trainings'] as $training)
                    <tr>
                        <td>
                            <strong>{{ $training['training'] }}</strong><br>
                            {{ $training['group'] }} · {{ $training['section'] }}
                        </td>
                        <td class="center">{{ $training['validity'] ?: '-' }}</td>
                        <td>
                            Terakhir: {{ $training['last_check'] ?? ($blankMissingRecords ? '' : '-') }}<br>
                            Berikutnya: {{ $training['next_check'] ?? ($blankMissingRecords ? '' : '-') }}<br>
                            Rencana: {{ $training['plan_check'] ?? ($blankMissingRecords ? '' : '-') }}
                        </td>
                        <td class="center">{{ $training['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        @empty
            <tbody>
                <tr>
                    <td class="empty" colspan="4">Belum ada data training.</td>
                </tr>
            </tbody>
        @endforelse
    </table>

    <div class="footer">Dokumen training crew</div>
</body>
</html>
