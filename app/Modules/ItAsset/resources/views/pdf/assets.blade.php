<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Inventaris IT</title>
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

        .heading p {
            color: #64748b;
            font-size: 9px;
            margin: 0;
        }

        .summary {
            color: #334155;
            font-size: 9px;
            margin-bottom: 10px;
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
            overflow-wrap: break-word;
            vertical-align: top;
        }

        thead th {
            background: #eaf1f8;
            color: #173b63;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
        }

        thead tr:first-child th {
            background: #173b63;
            color: #fff;
        }

        tbody tr:nth-child(even) {
            background: #f5f8fc;
        }

        .number,
        .code,
        .status {
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
            font-size: 8px;
            position: fixed;
            right: 0;
        }
    </style>
</head>
<body>
    <header class="heading">
        <h1>DAFTAR INVENTARIS IT</h1>
        <p>Dicetak {{ $generatedAt->format('d/m/Y H:i') }}</p>
    </header>

    <p class="summary">Total aset: <strong>{{ $assets->count() }}</strong></p>

    <table>
        <colgroup>
            <col style="width: 4%">
            <col style="width: 12%">
            <col style="width: 5%">
            <col style="width: 14%">
            <col style="width: 13%">
            <col style="width: 12%">
            <col style="width: 9%">
            <col style="width: 9%">
            <col style="width: 12%">
            <col style="width: 10%">
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2">NO</th>
                <th colspan="2">NAMA BARANG</th>
                <th rowspan="2">MERK BARANG</th>
                <th rowspan="2">SERIAL NUMBER</th>
                <th rowspan="2">USER</th>
                <th rowspan="2">DIVISI</th>
                <th rowspan="2">KONDISI</th>
                <th rowspan="2">KETERANGAN</th>
                <th rowspan="2">STATUS</th>
            </tr>
            <tr>
                <th>KATEGORI</th>
                <th>KODE</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assets as $asset)
                <tr>
                    <td class="number">{{ $loop->iteration }}</td>
                    <td>{{ $asset->category?->name ?? '-' }}</td>
                    <td class="code">{{ $asset->category?->code ?? '-' }}</td>
                    <td>{{ $asset->brand ?? '-' }}</td>
                    <td>{{ $asset->serial_number ?? '-' }}</td>
                    <td>{{ $asset->holder_name ?? '-' }}</td>
                    <td>{{ $asset->division ?? '-' }}</td>
                    <td>{{ $asset->condition ?? '-' }}</td>
                    <td>{{ $asset->notes ?? '-' }}</td>
                    <td class="status">{{ $asset->status }}</td>
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="10">Belum ada data aset.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Dokumen inventaris IT</div>
</body>
</html>
