<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 18px 16px 22px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: {{ $fontSize }}px;
            line-height: 1.22;
        }

        h1, p {
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .eyebrow {
            color: #1d4ed8;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        h1 {
            margin-top: 3px;
            font-size: 18px;
            line-height: 1.1;
        }

        .meta {
            margin-top: 4px;
            color: #64748b;
            font-size: 8px;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .summary td {
            width: 25%;
            border: 1px solid #dbe3ee;
            background: #f8fafc;
            padding: 6px 8px;
        }

        .summary span {
            display: block;
            color: #64748b;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary strong {
            display: block;
            margin-top: 2px;
            font-size: 13px;
            line-height: 1.05;
        }

        .filters,
        .note,
        .footer {
            color: #475569;
            font-size: 7px;
        }

        .filters {
            margin-bottom: 8px;
        }

        .filters strong,
        .note strong {
            color: #0f172a;
        }

        .note {
            border: 1px solid #dbe3ee;
            background: #f8fafc;
            padding: 6px 8px;
            margin: 8px 0;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.report thead {
            display: table-header-group;
        }

        table.report tr {
            page-break-inside: avoid;
        }

        table.report th {
            background: #12325b;
            color: #ffffff;
            border: 1px solid #12325b;
            padding: 4px 3px;
            font-size: 6px;
            line-height: 1.15;
            text-align: left;
            text-transform: uppercase;
            vertical-align: middle;
            word-break: normal;
        }

        table.report td {
            border: 1px solid #dbe3ee;
            padding: 4px 3px;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        table.report tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        table.report tbody tr.legacy td:first-child {
            border-left: 3px solid #f59e0b;
        }

        .cell-right {
            text-align: right;
        }

        .cell-center {
            text-align: center;
        }

        .footer {
            margin-top: 9px;
            text-align: right;
        }

        .matrix {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .matrix-title td {
            background: #12325b;
            color: #ffffff;
            border: 1px solid #12325b;
            padding: 5px 7px;
            font-size: 7px;
            font-weight: 700;
        }

        .matrix-label {
            width: 11%;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #dbe3ee;
            padding: 4px 5px;
            font-size: 6px;
            font-weight: 700;
            text-transform: uppercase;
            vertical-align: top;
        }

        .matrix-value {
            width: 14%;
            border: 1px solid #dbe3ee;
            padding: 4px 5px;
            vertical-align: top;
            word-break: normal;
            overflow-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="eyebrow">National Development Company</p>
        <h1>{{ $title }}</h1>
        <p class="meta">Generated {{ $generatedAt->format('M d, Y h:i A') }} | {{ number_format($summary['total']) }} project(s)</p>
    </div>

    <table class="summary" aria-label="Report summary">
        <tr>
            <td><span>Total projects</span><strong>{{ number_format($summary['total']) }}</strong></td>
            <td><span>PMS records</span><strong>{{ number_format($summary['pms']) }}</strong></td>
            <td><span>Legacy records</span><strong>{{ number_format($summary['legacy']) }}</strong></td>
            <td><span>Estimated cost</span><strong>PHP {{ number_format($summary['estimated_cost'], 2) }}</strong></td>
        </tr>
    </table>

    <p class="filters">
        <strong>Filters:</strong> {{ $filters ?: 'None' }}
    </p>

    @if(!empty($note))
        <p class="note"><strong>Extraction note:</strong> {{ $note }}</p>
    @endif

    @if($layout === 'matrix')
        @forelse($rows as $row)
            @php
                $fieldCells = collect($row['cells'])->slice(1)->values();
                $titleCell = $fieldCells->firstWhere('key', 'title');
                $codeCell = $fieldCells->firstWhere('key', 'project_code');
                $sourceCell = $fieldCells->firstWhere('key', 'record_source');
            @endphp
            <table class="matrix">
                <tr class="matrix-title">
                    <td colspan="8">
                        {{ $row['cells'][0]['value'] }}. {{ $codeCell['value'] ?? 'No code' }} - {{ $titleCell['value'] ?? 'Untitled project' }}
                        @if(!empty($sourceCell['value']) && $sourceCell['value'] !== 'N/A')
                            | {{ $sourceCell['value'] }}
                        @endif
                    </td>
                </tr>
                @foreach($fieldCells->chunk(4) as $chunk)
                    <tr>
                        @foreach($chunk as $cell)
                            @php $column = collect($columns)->firstWhere('key', $cell['key']); @endphp
                            <td class="matrix-label">{{ $column['header'] ?? $cell['key'] }}</td>
                            <td class="matrix-value cell-{{ $cell['align'] }}">{{ $cell['value'] }}</td>
                        @endforeach
                        @for($i = $chunk->count(); $i < 4; $i++)
                            <td class="matrix-label">&nbsp;</td>
                            <td class="matrix-value">&nbsp;</td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        @empty
            <table class="report">
                <tr>
                    <td class="cell-center" style="padding: 20px;">
                        No projects matched the selected report filters.
                    </td>
                </tr>
            </table>
        @endforelse
    @else
        <table class="report">
            <colgroup>
                @foreach($columns as $column)
                    <col style="width: {{ $column['width_percent'] }}%;">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th class="cell-{{ $column['align'] }}">{{ $column['header'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="{{ $row['is_legacy'] ? 'legacy' : '' }}">
                        @foreach($row['cells'] as $cell)
                            <td class="cell-{{ $cell['align'] }}">{{ $cell['value'] }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="cell-center" style="padding: 20px;">
                            No projects matched the selected report filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <p class="footer">Generated from PMS Reports using the current filters and selected columns.</p>
</body>
</html>
