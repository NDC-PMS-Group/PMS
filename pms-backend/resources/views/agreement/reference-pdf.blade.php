<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Draft Agreement Form Reference</title>
    <style>
        @page { margin: 28px 26px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.45;
        }
        h1, h2, p { margin: 0; }
        .header {
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .eyebrow {
            color: #2563eb;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        h1 {
            margin-top: 4px;
            font-size: 20px;
        }
        h2 {
            margin: 18px 0 8px;
            font-size: 14px;
        }
        .meta {
            margin-top: 5px;
            color: #64748b;
            font-size: 9px;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .summary td {
            width: 25%;
            border: 1px solid #dbe3ee;
            background: #f8fafc;
            padding: 8px;
            vertical-align: top;
        }
        .summary span,
        .party-table th {
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .summary strong {
            display: block;
            margin-top: 2px;
            font-size: 11px;
            color: #0f172a;
        }
        .party-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .party-table th {
            background: #eef2ff;
            border: 1px solid #cbd5e1;
            padding: 6px 5px;
            text-align: left;
        }
        .party-table td {
            border: 1px solid #dbe3ee;
            padding: 7px 5px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .party-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }
        .term {
            border: 1px solid #dbe3ee;
            background: #f8fafc;
            padding: 10px;
            white-space: pre-wrap;
        }
        .muted { color: #64748b; }
        .footer {
            margin-top: 14px;
            color: #64748b;
            font-size: 8px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="eyebrow">NDC Project Management System</p>
        <h1>Draft Agreement Form Reference</h1>
        <p class="meta">
            Generated {{ $generatedAt->format('M d, Y h:i A') }} for Legal agreement drafting reference.
        </p>
    </div>

    <table class="summary">
        <tr>
            <td>
                <span>Project Code</span>
                <strong>{{ $project->project_code ?: $project->id }}</strong>
            </td>
            <td>
                <span>Agreement Type</span>
                <strong>{{ $form->agreement_type ?: 'Not provided' }}</strong>
            </td>
            <td>
                <span>Status</span>
                <strong>{{ ucfirst((string) $form->status) }}</strong>
            </td>
            <td>
                <span>Submitted</span>
                <strong>{{ $form->submitted_at?->format('M d, Y') ?: 'Not submitted' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span>Project</span>
                <strong>{{ $project->title ?: 'Untitled project' }}</strong>
            </td>
            <td>
                <span>Prepared By</span>
                <strong>{{ $form->preparedBy?->full_name ?: $form->preparedBy?->name ?: 'Unknown' }}</strong>
            </td>
            <td>
                <span>Submitted By</span>
                <strong>{{ $form->submittedBy?->full_name ?: $form->submittedBy?->name ?: 'Unknown' }}</strong>
            </td>
        </tr>
    </table>

    <h2>Parties</h2>
    <table class="party-table">
        <thead>
            <tr>
                <th style="width: 10%;">Party</th>
                <th style="width: 18%;">Company</th>
                <th style="width: 24%;">Office Address</th>
                <th style="width: 16%;">Authorized Signatory</th>
                <th style="width: 12%;">Position</th>
                <th style="width: 10%;">CTC / Passport</th>
                <th style="width: 10%;">Issue Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse($form->parties ?? [] as $party)
                <tr>
                    <td>{{ $party['label'] ?? 'Party' }}</td>
                    <td>{{ $party['company_name'] ?? 'Not provided' }}</td>
                    <td>{!! nl2br(e($party['office_address'] ?? 'Not provided')) !!}</td>
                    <td>{{ $party['authorized_signatory'] ?? 'Not provided' }}</td>
                    <td>{{ $party['position'] ?? 'Not provided' }}</td>
                    <td>{{ $party['ctc_passport_id'] ?? 'Not provided' }}</td>
                    <td>{{ $party['issue_date_place'] ?? 'Not provided' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 18px;">
                        No party details were submitted.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Term Sheet</h2>
    <div class="term">{{ $form->term_sheet ?: 'Not provided' }}</div>

    <p class="footer">Generated from the submitted Draft Agreement Form in NDC PMS.</p>
</body>
</html>
