<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #1e3a8a;
            font-size: 18px;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 13px;
            color: #4b5563;
            margin: 0 0 8px 0;
        }
        .meta {
            font-size: 10px;
            color: #6b7280;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin: 15px 0 8px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }
        td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-approved { background-color: #dcfce7; color: #166534; }
        .badge-pending { background-color: #fef9c3; color: #854d0e; }
        .badge-urgent { background-color: #fee2e2; color: #991b1b; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $institution_name ?? 'Enterprise Document System' }}</h1>
        <h2>Integrated Document Repository & Archive Management System</h2>
        <div class="meta">
            <strong>Report:</strong> {{ $title }} | <strong>Period:</strong> {{ $period }} | <strong>Generated:</strong> {{ $generated_at }}
        </div>
    </div>

    <div class="section-title">Institutional Documents Registry (Recent)</div>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Reference</th>
                <th style="width: 35%;">Title</th>
                <th style="width: 15%;">Type</th>
                <th style="width: 15%;">Department</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 10%;">Version</th>
            </tr>
        </thead>
        <tbody>
            @forelse($documents as $doc)
            <tr>
                <td><strong>{{ $doc->reference_number }}</strong></td>
                <td>{{ $doc->title }}</td>
                <td>{{ $doc->documentType?->name ?? 'N/A' }}</td>
                <td>{{ $doc->department?->name ?? 'N/A' }}</td>
                <td><span class="badge {{ $doc->status === 'approved' ? 'badge-approved' : 'badge-pending' }}">{{ $doc->status }}</span></td>
                <td>v{{ $doc->current_version }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; color: #6b7280;">No document records found in this reporting window.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Incoming Correspondence Registry (Recent)</div>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Reference</th>
                <th style="width: 25%;">Sender</th>
                <th style="width: 30%;">Subject</th>
                <th style="width: 10%;">Priority</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 10%;">Date Received</th>
            </tr>
        </thead>
        <tbody>
            @forelse($incoming as $inc)
            <tr>
                <td><strong>{{ $inc->reference_number }}</strong></td>
                <td>{{ $inc->sender_name }} ({{ $inc->sender_organization ?? 'External' }})</td>
                <td>{{ $inc->subject }}</td>
                <td><span class="badge {{ $inc->priority === 'urgent' ? 'badge-urgent' : 'badge-pending' }}">{{ $inc->priority }}</span></td>
                <td>{{ $inc->status }}</td>
                <td>{{ $inc->date_received?->format('Y-m-d') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; color: #6b7280;">No correspondence records found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Confidential Institutional Record — {{ $institution_name ?? 'Enterprise DMS' }} — Page 1
    </div>
</body>
</html>
