<?php

namespace App\Services;

use App\Models\LegacyImportBatch;
use App\Models\Project;
use App\Models\ProjectLegacyDetail;
use App\Models\ProjectMember;
use App\Models\ProjectStage;
use App\Models\ProjectStageHistory;
use App\Models\ProjectStatus;
use App\Models\ProjectStatusHistory;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LegacyProjectImportService
{
    private const SOURCE_STATUS_MAP = [
        'identified' => ['stage' => 'Intake', 'status' => 'Pre-screening / KYC'],
        'under evaluation' => ['stage' => 'Due Diligence', 'status' => 'Due Diligence Ongoing'],
        'for implementation' => ['stage' => 'Implementation & Monitoring', 'status' => 'Milestones Setup'],
        'on-going implementation' => ['stage' => 'Implementation & Monitoring', 'status' => 'Implementation Ongoing'],
        'ongoing implementation' => ['stage' => 'Implementation & Monitoring', 'status' => 'Implementation Ongoing'],
        'operational' => ['stage' => 'Implementation & Monitoring', 'status' => 'Monitoring Ongoing'],
        'under dissolution/divestment' => ['stage' => 'Divestment', 'status' => 'For Divestment'],
        'under dissolution / divestment' => ['stage' => 'Divestment', 'status' => 'For Divestment'],
        'shelved projects' => ['stage' => 'Intake', 'status' => 'On Hold'],
        'shelved' => ['stage' => 'Intake', 'status' => 'On Hold'],
    ];

    private const REQUIRED_HEADERS = [
        'title' => ['projecttitleorname', 'projecttitle', 'projectname'],
        'status' => ['statusmilestone', 'status'],
        'sector' => ['sector'],
        'location' => ['location'],
        'cost' => ['indicativeprojectcost', 'projectcost', 'cost'],
        'fund_released' => ['fundreleased', 'releasedfund'],
        'partner' => ['proponentpartner', 'proponent', 'partner'],
        'remarks' => ['remarks'],
    ];

    public function preview(UploadedFile $file): array
    {
        $parsed = $this->parseWorkbook($file);
        $rows = $this->decorateDuplicates($parsed['rows']);

        return [
            'file_name' => $file->getClientOriginalName(),
            'header_row' => $parsed['header_row'],
            'rows' => $rows,
            'summary' => $this->summarizeRows($rows),
            'warnings' => $parsed['warnings'],
        ];
    }

    public function commit(UploadedFile $file, User $user): array
    {
        $parsed = $this->parseWorkbook($file);
        $rows = $this->decorateDuplicates($parsed['rows']);

        return DB::transaction(function () use ($file, $user, $parsed, $rows) {
            $created = new EloquentCollection();
            $skipped = 0;
            $seenFingerprints = [];

            $batch = LegacyImportBatch::create([
                'file_name' => $file->getClientOriginalName(),
                'imported_by' => $user->id,
                'total_rows' => count($rows),
                'created_count' => 0,
                'skipped_count' => 0,
                'status_summary' => $this->summarizeRows($rows),
                'warnings' => $parsed['warnings'],
            ]);

            foreach ($rows as $row) {
                if (!$row['is_importable'] || $row['is_duplicate'] || isset($seenFingerprints[$row['row_fingerprint']])) {
                    $skipped++;
                    continue;
                }

                $project = $this->createLegacyProject($row, $batch, $user);
                $created->push($project);
                $seenFingerprints[$row['row_fingerprint']] = true;
            }

            $batch->update([
                'created_count' => $created->count(),
                'skipped_count' => $skipped,
            ]);

            return [
                'batch' => $batch->fresh('importedBy'),
                'created_count' => $created->count(),
                'skipped_count' => $skipped,
                'summary' => $this->summarizeRows($rows),
                'projects' => $created->load([
                    'projectType', 'industry', 'sector', 'currentStage', 'status',
                    'projectOfficer', 'workgroupHead', 'creator', 'legacyDetail.batch',
                    'legacyDetail.completedBy',
                ]),
            ];
        });
    }

    private function parseWorkbook(UploadedFile $file): array
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $warnings = [];
        $headerRow = null;
        $columns = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            $headers = [];
            for ($column = 1; $column <= $highestColumn; $column++) {
                $headers[$column] = $this->normalizeHeader($this->cellValue($sheet, $column, $row));
            }

            $detected = $this->detectColumns($headers);
            if (isset($detected['title'])) {
                $headerRow = $row;
                $columns = $detected;
                break;
            }
        }

        if (!$headerRow) {
            return [
                'header_row' => null,
                'rows' => [],
                'warnings' => ['Could not find a header row with PROJECT TITLE or NAME.'],
            ];
        }

        foreach (['title', 'status'] as $requiredKey) {
            if (!isset($columns[$requiredKey])) {
                $warnings[] = "Missing expected column: {$requiredKey}.";
            }
        }

        $rows = [];
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $values = [];
            foreach (array_keys(self::REQUIRED_HEADERS) as $key) {
                $values[$key] = isset($columns[$key])
                    ? $this->cleanValue($this->cellValue($sheet, $columns[$key], $row))
                    : null;
            }

            if ($this->isBlankRow($values)) {
                continue;
            }

            $rows[] = $this->prepareRow($row, $file->getClientOriginalName(), $values);
        }

        return [
            'header_row' => $headerRow,
            'rows' => $rows,
            'warnings' => $warnings,
        ];
    }

    private function detectColumns(array $normalizedHeaders): array
    {
        $columns = [];
        foreach ($normalizedHeaders as $column => $header) {
            if ($header === '') {
                continue;
            }

            foreach (self::REQUIRED_HEADERS as $key => $candidates) {
                if (isset($columns[$key])) {
                    continue;
                }

                if (in_array($header, $candidates, true)) {
                    $columns[$key] = $column;
                }
            }
        }

        return $columns;
    }

    private function prepareRow(int $rowNumber, string $fileName, array $values): array
    {
        $title = $values['title'] ?: null;
        $sourceStatus = $values['status'] ?: null;
        $statusMapping = $this->mapStatus($sourceStatus);
        $cost = $this->parseMoney($values['cost']);
        $fundReleased = $this->parseMoney($values['fund_released']);
        $warnings = array_values(array_filter([
            ...$statusMapping['warnings'],
            ...$cost['warnings'],
            ...$fundReleased['warnings'],
            $title ? null : 'Missing project title.',
        ]));

        $fingerprint = hash('sha256', mb_strtolower(trim(implode('|', [
            $title,
            $sourceStatus,
            $values['sector'],
            $values['location'],
            $values['cost'],
            $values['fund_released'],
            $values['partner'],
        ]))));

        return [
            'source_row' => $rowNumber,
            'source_file' => $fileName,
            'title' => $title,
            'source_status_raw' => $sourceStatus,
            'mapped_stage' => $statusMapping['stage'],
            'mapped_status' => $statusMapping['status'],
            'sector' => $values['sector'],
            'location' => $values['location'],
            'source_cost_raw' => $values['cost'],
            'estimated_cost' => $cost['amount'],
            'source_fund_released_raw' => $values['fund_released'],
            'fund_released' => $fundReleased['amount'],
            'source_partner_raw' => $values['partner'],
            'source_remarks' => $values['remarks'],
            'parse_warnings' => $warnings,
            'row_fingerprint' => $fingerprint,
            'is_importable' => (bool) $title,
            'is_duplicate' => false,
            'duplicate_reason' => null,
        ];
    }

    private function decorateDuplicates(array $rows): array
    {
        $fingerprints = collect($rows)->pluck('row_fingerprint')->filter()->values();
        $titles = collect($rows)->pluck('title')->filter()->map(fn ($title) => mb_strtolower(trim($title)))->values();

        $existingFingerprints = ProjectLegacyDetail::query()
            ->whereIn('row_fingerprint', $fingerprints)
            ->pluck('row_fingerprint')
            ->all();

        $existingTitles = Project::query()
            ->where('is_legacy', true)
            ->whereIn(DB::raw('LOWER(title)'), $titles)
            ->pluck('title')
            ->map(fn ($title) => mb_strtolower(trim($title)))
            ->all();

        $seen = [];

        return array_map(function (array $row) use (&$seen, $existingFingerprints, $existingTitles) {
            $titleKey = mb_strtolower(trim((string) $row['title']));
            if (in_array($row['row_fingerprint'], $existingFingerprints, true)) {
                $row['is_duplicate'] = true;
                $row['duplicate_reason'] = 'Already imported from a matching source row.';
            } elseif ($titleKey !== '' && in_array($titleKey, $existingTitles, true)) {
                $row['is_duplicate'] = true;
                $row['duplicate_reason'] = 'A legacy project with the same title already exists.';
            } elseif (isset($seen[$row['row_fingerprint']])) {
                $row['is_duplicate'] = true;
                $row['duplicate_reason'] = 'Duplicate row within this upload.';
            }

            $seen[$row['row_fingerprint']] = true;

            return $row;
        }, $rows);
    }

    private function createLegacyProject(array $row, LegacyImportBatch $batch, User $user): Project
    {
        $stage = ProjectStage::firstOrCreate(
            ['name' => $row['mapped_stage']],
            ['sequence_order' => 999, 'description' => 'Imported legacy stage fallback.', 'is_active' => true]
        );
        $status = ProjectStatus::firstOrCreate(
            ['name' => $row['mapped_status']],
            ['color_code' => '#64748B', 'is_active' => true]
        );
        $sector = $row['sector']
            ? Sector::firstOrCreate(['name' => $row['sector']], ['description' => 'Imported from legacy project list.'])
            : null;

        $payload = [
            'project_code' => $this->generateProjectCode('spg_ndc_own'),
            'title' => $row['title'],
            'description' => $row['source_remarks'] ?: 'Legacy project imported from the NDC Project List. Details pending staff completion.',
            'process_track' => 'spg_ndc_own',
            'origin_track' => 'spg_ndc_own',
            'lifecycle_phase' => $this->lifecyclePhaseForStage($row['mapped_stage']),
            'lifecycle_phase_started_at' => now(),
            'sector_id' => $sector?->id,
            'estimated_cost' => $row['estimated_cost'],
            'actual_cost' => $row['fund_released'],
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'location_address' => $row['location'],
            'proponent_name' => $row['source_partner_raw'],
            'is_svf' => false,
            'is_legacy' => true,
            'is_archived' => $this->isShelvedStatus($row['source_status_raw']),
            'is_deleted' => false,
            'created_by' => $user->id,
        ];

        $project = Project::create($payload);

        ProjectStageHistory::create([
            'project_id' => $project->id,
            'to_stage_id' => $stage->id,
            'changed_by' => $user->id,
            'change_reason' => 'Legacy project imported',
        ]);

        ProjectStatusHistory::create([
            'project_id' => $project->id,
            'to_status_id' => $status->id,
            'changed_by' => $user->id,
            'change_reason' => 'Legacy project imported',
        ]);

        if ($user->default_role_id) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project->id, 'user_id' => $user->id],
                [
                    'role_id' => $user->default_role_id,
                    'assignment_type' => 'owner',
                    'can_view' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_approve' => true,
                    'can_manage_members' => true,
                    'assigned_by' => $user->id,
                    'removed_at' => null,
                ]
            );
        }

        ProjectLegacyDetail::create([
            'project_id' => $project->id,
            'legacy_import_batch_id' => $batch->id,
            'source_row' => $row['source_row'],
            'source_file' => $row['source_file'],
            'source_status_raw' => $row['source_status_raw'],
            'source_cost_raw' => $row['source_cost_raw'],
            'source_fund_released_raw' => $row['source_fund_released_raw'],
            'source_partner_raw' => $row['source_partner_raw'],
            'source_remarks' => $row['source_remarks'],
            'row_fingerprint' => $row['row_fingerprint'],
            'parse_warnings' => $row['parse_warnings'],
            'detail_status' => ProjectLegacyDetail::STATUS_NEEDS_DETAILS,
        ]);

        return $project->fresh();
    }

    private function summarizeRows(array $rows): array
    {
        $collection = collect($rows);

        return [
            'total_rows' => $collection->count(),
            'importable_rows' => $collection->where('is_importable', true)->where('is_duplicate', false)->count(),
            'duplicate_rows' => $collection->where('is_duplicate', true)->count(),
            'missing_title_rows' => $collection->where('is_importable', false)->count(),
            'warning_rows' => $collection->filter(fn ($row) => count($row['parse_warnings'] ?? []) > 0)->count(),
            'by_source_status' => $this->countsBy($collection, 'source_status_raw'),
            'by_mapped_status' => $this->countsBy($collection, 'mapped_status'),
        ];
    }

    private function countsBy(Collection $rows, string $key): array
    {
        return $rows
            ->groupBy(fn ($row) => $row[$key] ?: 'Blank')
            ->map(fn ($items) => $items->count())
            ->sortKeys()
            ->all();
    }

    private function mapStatus(?string $sourceStatus): array
    {
        $normalized = mb_strtolower(trim((string) $sourceStatus));
        $mapping = self::SOURCE_STATUS_MAP[$normalized] ?? ['stage' => 'Intake', 'status' => 'On Hold'];

        return [
            ...$mapping,
            'warnings' => $normalized && !isset(self::SOURCE_STATUS_MAP[$normalized])
                ? ["Unknown source status '{$sourceStatus}' mapped to Intake / On Hold."]
                : [],
        ];
    }

    private function parseMoney(?string $value): array
    {
        $raw = trim((string) $value);
        if ($raw === '' || in_array(mb_strtolower($raw), ['n/a', 'na', 'none', '-'], true)) {
            return ['amount' => null, 'warnings' => []];
        }

        $normalized = str_replace([',', '₱', 'php', 'PHP'], '', $raw);
        if (is_numeric($normalized)) {
            return ['amount' => round((float) $normalized, 2), 'warnings' => []];
        }

        if (preg_match('/([\d]+(?:\.\d+)?)\s*(bn|billion|b|mn|million|m)\b/i', $normalized, $matches)) {
            $number = (float) $matches[1];
            $unit = mb_strtolower($matches[2]);
            $multiplier = in_array($unit, ['bn', 'billion', 'b'], true) ? 1_000_000_000 : 1_000_000;

            return ['amount' => round($number * $multiplier, 2), 'warnings' => []];
        }

        return ['amount' => null, 'warnings' => ["Could not parse amount '{$raw}'. Raw value was preserved."]];
    }

    private function generateProjectCode(string $track): string
    {
        $prefix = match ($track) {
            'spg_traditional', 'spg_ndc_own', 'spg_jv' => 'SPG',
            default => 'BDG',
        };
        $year = date('Y');
        $maxNumber = Project::withTrashed()
            ->where('project_code', 'like', "{$prefix}-{$year}-%")
            ->pluck('project_code')
            ->map(function ($code) use ($prefix, $year) {
                $pattern = '/^' . preg_quote($prefix, '/') . '-' . preg_quote((string) $year, '/') . '-(\d+)$/';
                return preg_match($pattern, (string) $code, $matches) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        return "{$prefix}-{$year}-" . str_pad((string) ($maxNumber + 1), 3, '0', STR_PAD_LEFT);
    }

    private function lifecyclePhaseForStage(string $stage): string
    {
        return match ($stage) {
            'Implementation & Monitoring' => 'implementation_monitoring',
            'Divestment' => 'divestment',
            'Completion' => 'completed',
            default => 'development',
        };
    }

    private function isShelvedStatus(?string $status): bool
    {
        return str_contains(mb_strtolower((string) $status), 'shelved');
    }

    private function cellValue($sheet, int $column, int $row): ?string
    {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        $value = $sheet->getCell($coordinate)->getFormattedValue();

        return is_scalar($value) ? (string) $value : null;
    }

    private function cleanValue(?string $value): ?string
    {
        $cleaned = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $cleaned === '' ? null : $cleaned;
    }

    private function normalizeHeader(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $value));
    }

    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->cleanValue($value) !== null) {
                return false;
            }
        }

        return true;
    }
}
