<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Monitoring Compliance Report</title>
    <style>
        @page { margin: 24px 22px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 10px;
            line-height: 1.35;
        }
        h1, h2, p { margin: 0; }
        .header {
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .eyebrow {
            color: #2563eb;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        h1 {
            margin-top: 3px;
            font-size: 20px;
        }
        .meta {
            margin-top: 5px;
            color: #64748b;
            font-size: 9px;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .summary td {
            width: 25%;
            border: 1px solid #dbe3ee;
            background: #f8fafc;
            padding: 8px;
        }
        .summary span {
            display: block;
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .summary strong {
            display: block;
            margin-top: 2px;
            font-size: 16px;
        }
        .filters {
            margin-bottom: 10px;
            color: #475569;
            font-size: 9px;
        }
        .filters strong {
            color: #0f172a;
        }
        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.report th {
            background: #1d4ed8;
            color: #fff;
            padding: 6px 5px;
            border: 1px solid #1e40af;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
        }
        table.report td {
            border: 1px solid #dbe3ee;
            padding: 6px 5px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.report tbody tr:nth-child(even) td {
            background: #f8fafc;
        }
        .status {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 10px;
            background: #e2e8f0;
            color: #334155;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-submitted { background: #ede9fe; color: #5b21b6; }
        .status-returned { background: #fee2e2; color: #991b1b; }
        .status-accepted { background: #dcfce7; color: #166534; }
        .muted { color: #64748b; }
        .amount, .number { text-align: right; white-space: nowrap; }
        .narrative {
            color: #334155;
            font-size: 8.5px;
        }
        .footer {
            margin-top: 12px;
            color: #64748b;
            font-size: 8px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="eyebrow">NDC Project Management System</p>
        <h1>Monitoring Compliance Report</h1>
        <p class="meta">Generated {{ $generatedAt->format('M d, Y h:i A') }}</p>
    </div>

    <table class="summary" aria-label="Report summary">
        <tr>
            <td><span>Total reports</span><strong>{{ number_format($summary['total']) }}</strong></td>
            <td><span>Needs review</span><strong>{{ number_format($summary['needs_review']) }}</strong></td>
            <td><span>Returned</span><strong>{{ number_format($summary['returned']) }}</strong></td>
            <td><span>Accepted</span><strong>{{ number_format($summary['accepted']) }}</strong></td>
        </tr>
    </table>

    <p class="filters">
        @foreach($filters as $label => $value)
            <strong>{{ $label }}:</strong> {{ $value }}@if(! $loop->last) &nbsp; | &nbsp; @endif
        @endforeach
    </p>

    <table class="report">
        <thead>
            <tr>
                <th style="width: 9%;">Project</th>
                <th style="width: 14%;">Title</th>
                <th style="width: 9%;">Type</th>
                <th style="width: 9%;">Period</th>
                <th style="width: 8%;">Status</th>
                <th style="width: 10%;">Employment</th>
                <th style="width: 10%;">Financial</th>
                <th style="width: 18%;">Progress / Narrative</th>
                <th style="width: 8%;">Submitted</th>
                <th style="width: 5%;">Review</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $report)
                <tr>
                    <td>
                        <strong>{{ $report->project?->project_code ?: 'N/A' }}</strong><br>
                        <span class="muted">{{ $report->project?->proponent_name ?: 'No proponent' }}</span>
                    </td>
                    <td>
                        {{ $report->project?->title ?: 'Untitled project' }}<br>
                        <span class="muted">{{ match ($report->project?->record_type) { 'project' => 'NDC Project', 'investment' => 'Investment', default => 'Needs classification' } }}
                        @if($report->project?->investment_status)
                            · {{ \App\Support\InvestmentLifecycle::LABELS[$report->project->investment_status] }}
                        @endif
                        </span>
                    </td>
                    <td>
                        {{ $typeLabel($report->compliance_type) }}<br>
                        <span class="muted">Q{{ $report->quarter }} {{ $report->reporting_year }}</span>
                    </td>
                    <td>
                        {{ $reportPeriodStart($report) ?: 'N/A' }}<br>
                        <span class="muted">to {{ $reportPeriodEnd($report) ?: 'N/A' }}</span>
                    </td>
                    <td><span class="status status-{{ $report->status }}">{{ ucfirst($report->status) }}</span></td>
                    <td>
                        @if($report->compliance_type === 'employment')
                            Generated: <strong>{{ number_format((int) $report->jobs_generated) }}</strong><br>
                            <span class="muted">M {{ number_format((int) $report->jobs_generated_male) }} / F {{ number_format((int) $report->jobs_generated_female) }}</span><br>
                            Retained: <strong>{{ number_format((int) $report->jobs_retained) }}</strong><br>
                            <span class="muted">M {{ number_format((int) $report->jobs_retained_male) }} / F {{ number_format((int) $report->jobs_retained_female) }}</span>
                        @else
                            <span class="muted">N/A</span>
                        @endif
                    </td>
                    <td>
                        @if($report->compliance_type === 'financial')
                            Revenue:<br><strong>PHP {{ number_format((float) $report->revenue, 2) }}</strong><br>
                            Remittance:<br><strong>PHP {{ number_format((float) $report->remittance, 2) }}</strong>
                        @else
                            <span class="muted">N/A</span>
                        @endif
                    </td>
                    <td class="narrative">
                        @if($report->compliance_type === 'progress')
                            <strong>Milestones:</strong> {{ $report->milestones ?: 'N/A' }}<br>
                            <strong>Impact:</strong> {{ $report->impact ?: 'N/A' }}<br>
                            <strong>Narrative:</strong> {{ $report->monitoring_narrative ?: 'N/A' }}
                        @else
                            <span class="muted">N/A</span>
                        @endif
                    </td>
                    <td>
                        {{ $report->submittedBy?->full_name ?: 'Unknown' }}<br>
                        <span class="muted">{{ $report->submitted_at?->format('M d, Y') ?: 'N/A' }}</span>
                    </td>
                    <td>
                        {{ $report->reviewedBy?->full_name ?: 'N/A' }}<br>
                        <span class="muted">{{ $report->reviewed_at?->format('M d, Y') ?: '' }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center; padding: 24px;">
                        No monitoring compliance reports matched the selected filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Generated from PMS Monitoring Compliance using the current filters.</p>
</body>
</html>
