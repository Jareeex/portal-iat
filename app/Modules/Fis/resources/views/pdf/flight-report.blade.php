<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Flight Information Sheet — {{ $flight->flight_no }}</title>
    <style>
        @page {
            margin: 24px 28px 36px;
        }

        body {
            color: #111;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
        }

        .hero {
            width: 100%;
            height: 100px;
            margin-bottom: 12px;
            background: #294f80;
            color: #fff;
        }

        .hero td {
            border: 0;
            vertical-align: middle;
        }

        .hero-title {
            width: 23%;
            padding: 10px 12px;
            font-size: 18px;
            font-weight: bold;
            line-height: 1.2;
        }

        .helicopter {
            width: 57%;
            padding: 4px;
            text-align: center;
        }

        .helicopter img {
            max-width: 100%;
            max-height: 90px;
        }

        .helicopter-placeholder {
            color: #d7e5f4;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .logo {
            width: 20%;
            padding: 8px;
            text-align: center;
        }

        .logo img {
            max-width: 100%;
            max-height: 48px;
            background: #fff;
        }

        .logo-placeholder {
            color: #fff;
            font-size: 10px;
            font-weight: bold;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .meta {
            margin: 8px 0 11px;
        }

        .meta td {
            border: 0;
            padding: 2px 5px;
            vertical-align: top;
        }

        .meta-label {
            font-weight: bold;
        }

        h2 {
            margin: 10px 0 3px;
            font-size: 10px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #111;
            padding: 3px 4px;
            overflow-wrap: break-word;
            vertical-align: top;
        }

        .data-table thead th {
            background: #294f80;
            color: #fff;
            font-size: 7px;
            text-align: center;
        }

        .center {
            text-align: center;
        }

        .duration-row td {
            font-weight: bold;
        }

        .duration-row td:first-child {
            text-align: right;
        }

        .airport-info {
            margin-top: 3px;
            border: 1px solid #111;
            padding: 5px;
        }

        .airport-info h2 {
            margin: 0 0 4px;
        }

        .airport-line {
            margin: 2px 0;
        }

        .section {
            margin-top: 10px;
        }

        .other-title {
            background: #294f80;
            color: #fff;
            font-weight: bold;
            padding: 4px;
        }

        .passenger-page {
            page-break-before: always;
        }

        .passenger-page h2 {
            margin: 0 0 4px;
        }

        .passenger-table th {
            text-align: left;
        }

        .empty {
            color: #555;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="hero">
        <tr>
            <td class="hero-title">Flight<br>Information<br>Sheet</td>
            <td class="helicopter">
                @if ($helicopterImage)
                    <img src="{{ $helicopterImage }}" alt="Aircraft">
                @else
                    <span class="helicopter-placeholder">AIRCRAFT PHOTO</span>
                @endif
            </td>
            <td class="logo">
                @if ($iatLogo)
                    <img src="{{ $iatLogo }}" alt="Indonesia Air Transport">
                @else
                    <span class="logo-placeholder">INDONESIA AIR</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td width="25%"><span class="meta-label">Flight No:</span><br>{{ $flight->flight_no }}</td>
            <td width="25%"><span class="meta-label">Aircraft Type:</span><br>{{ $flight->aircraftRegistration->aircraft_type }}</td>
            <td width="25%"><span class="meta-label">Registration:</span><br>{{ $flight->aircraftRegistration->registration }}</td>
            <td width="25%"><span class="meta-label">Seat Capacity:</span><br>{{ $flight->aircraftRegistration->seat_capacity }}</td>
        </tr>
        <tr>
            <td><span class="meta-label">Route:</span><br>{{ collect($flight->schedules)->map(fn ($leg) => strtoupper($leg['from']).' - '.strtoupper($leg['to']))->implode(' / ') }}</td>
            <td><span class="meta-label">Date:</span><br>{{ $flight->date->format('d F Y') }}</td>
            <td colspan="2"><span class="meta-label">Chartered By:</span><br>{{ $flight->chartered_by }}</td>
        </tr>
    </table>

    <h2>Flight Schedule</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th width="20%">DATE</th>
                <th width="19%">ROUTES</th>
                <th width="23%">ETD / ETA</th>
                <th width="16%">FLIGHT TIME</th>
                <th width="22%">REMARKS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($flight->schedules as $leg)
                <tr>
                    <td class="center">{{ \Carbon\Carbon::parse($leg['date'])->format('d F Y') }}</td>
                    <td class="center">{{ strtoupper($leg['from']) }} - {{ strtoupper($leg['to']) }}</td>
                    <td class="center">{{ \Carbon\Carbon::parse($leg['etd'])->format('H:i') }} / {{ \Carbon\Carbon::parse($leg['eta'])->format('H:i') }}</td>
                    <td class="center">{{ intdiv($leg['duration_minutes'] ?? 0, 60) }}:{{ sprintf('%02d', ($leg['duration_minutes'] ?? 0) % 60) }}</td>
                    <td>{{ $leg['remarks'] ?: '-' }}</td>
                </tr>
            @endforeach
            <tr class="duration-row">
                <td colspan="3">Duration Flight</td>
                <td colspan="2" class="center">{{ intdiv($flight->total_duration_minutes, 60) }} Jam {{ sprintf('%02d', $flight->total_duration_minutes % 60) }} Menit</td>
            </tr>
        </tbody>
    </table>

    <div class="airport-info">
        <h2>Airport Information:</h2>
        @php
            $shownAirports = [];
        @endphp
        @foreach ($flight->schedules as $leg)
            @foreach (['from', 'to'] as $endpoint)
                @php
                    $code = strtoupper($leg[$endpoint]);
                    $airport = $airports[$code] ?? null;
                @endphp
                @if (! isset($shownAirports[$code]))
                    @php($shownAirports[$code] = true)
                    <p class="airport-line">
                        {{ $code }} — {{ $airport?->municipality ?? '' }}{{ $airport?->municipality && $airport?->country_name ? ', ' : '' }}{{ $airport?->country_name ?? '' }}{{ $airport?->name ? ' · '.$airport->name : '' }}
                    </p>
                @endif
            @endforeach
        @endforeach
    </div>

    <div class="section">
        <h2>Crew Detail</h2>
        <table class="data-table">
            <thead><tr><th>NAME</th><th>POSITION</th><th>PHONE</th><th>REMARKS</th></tr></thead>
            <tbody>
                @forelse ($flight->crews ?? [] as $crew)
                    <tr>
                        <td>{{ $crew['name'] }}</td><td>{{ $crew['position'] ?: '-' }}</td>
                        <td>{{ $crew['phone'] ?: '-' }}</td><td>{{ $crew['remarks'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="4">-</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Handling Agent</h2>
        <table class="data-table">
            <thead><tr><th>LOCATION</th><th>COMPANY</th><th>CONTACT PERSON</th><th>PHONE</th></tr></thead>
            <tbody>
                @forelse ($flight->handling_agents ?? [] as $agent)
                    <tr>
                        <td>{{ $agent['location'] }}</td><td>{{ $agent['company'] ?: '-' }}</td>
                        <td>{{ $agent['contact_person'] ?: '-' }}</td><td>{{ $agent['phone'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="4">-</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Crew Accommodation</h2>
        <table class="data-table">
            <thead><tr><th>LOCATION</th><th>HOTEL</th><th>ADDRESS</th><th>PHONE</th></tr></thead>
            <tbody>
                @forelse ($flight->accommodations ?? [] as $stay)
                    <tr>
                        <td>{{ $stay['location'] }}</td><td>{{ $stay['hotel'] }}</td>
                        <td>{{ $stay['address'] ?: '-' }}</td><td>{{ $stay['phone'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="4">-</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Other Information</h2>
        <table class="data-table">
            <thead><tr><th>FLIGHT OPERATIONS MANAGEMENT</th><th>STATUS</th></tr></thead>
            <tbody>
                @foreach ([
                    'flight_sec_clearance' => 'Flight Security Clearance',
                    'flight_approval' => 'Flight Approval',
                    'landing_permit' => 'Landing Permit',
                    'overflight_permit' => 'Overflight Permit',
                    'fuel_on_board' => 'Fuel On Board',
                    'notam' => 'Notam',
                    'parking_stand' => 'Parking Stand',
                ] as $key => $label)
                    <tr><td>{{ $label }}</td><td>{{ $flight->other[$key] ?? '-' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="passenger-page">
        <h2>Passenger</h2>
        <table class="data-table passenger-table">
            <thead><tr><th width="60%">NAME</th><th width="40%">REMARKS</th></tr></thead>
            <tbody>
                @forelse ($flight->passengers ?? [] as $passenger)
                    <tr>
                        <td>{{ $passenger['name'] }}</td>
                        <td>{{ $passenger['remarks'] ?: collect($flight->schedules)->map(fn ($leg) => strtoupper($leg['from']).'-'.strtoupper($leg['to']))->implode(', ') }}</td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="2">Belum ada data penumpang.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
