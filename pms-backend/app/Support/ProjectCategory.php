<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class ProjectCategory
{
    public const TRADITIONAL_EXTERNAL = 'traditional_external';
    public const STARTUP_VENTURE = 'startup_venture';
    public const JOINT_VENTURE = 'joint_venture';
    public const NDC_INITIATED = 'ndc_initiated';

    public static function baselineKeys(): array
    {
        return [
            self::TRADITIONAL_EXTERNAL,
            self::STARTUP_VENTURE,
            self::JOINT_VENTURE,
            self::NDC_INITIATED,
        ];
    }

    public static function categoryKey(?string $track, bool $isStartup = false): string
    {
        if ($isStartup || in_array($track, [self::STARTUP_VENTURE, 'bdg_svf'], true)) {
            return self::STARTUP_VENTURE;
        }

        return match ($track) {
            self::TRADITIONAL_EXTERNAL, 'bdg_investment', 'spg_traditional' => self::TRADITIONAL_EXTERNAL,
            self::JOINT_VENTURE, 'spg_jv' => self::JOINT_VENTURE,
            self::NDC_INITIATED, 'spg_ndc_own' => self::NDC_INITIATED,
            default => (string) ($track ?: self::TRADITIONAL_EXTERNAL),
        };
    }

    public static function workflowKey(string $categoryKey): string
    {
        return match ($categoryKey) {
            self::TRADITIONAL_EXTERNAL => 'bdg_investment',
            self::STARTUP_VENTURE => 'bdg_svf',
            self::JOINT_VENTURE => 'spg_jv',
            self::NDC_INITIATED => 'spg_ndc_own',
            default => $categoryKey,
        };
    }

    public static function storageTrack(string $categoryKey): string
    {
        return match ($categoryKey) {
            self::TRADITIONAL_EXTERNAL, self::STARTUP_VENTURE => 'bdg_investment',
            self::JOINT_VENTURE => 'spg_jv',
            self::NDC_INITIATED => 'spg_ndc_own',
            default => $categoryKey,
        };
    }

    public static function isStartup(string $categoryKey): bool
    {
        return in_array($categoryKey, [self::STARTUP_VENTURE, 'bdg_svf'], true);
    }

    public static function label(string $categoryKey): string
    {
        return match (self::categoryKey($categoryKey, self::isStartup($categoryKey))) {
            self::TRADITIONAL_EXTERNAL => 'Traditional / External Investment',
            self::STARTUP_VENTURE => 'Startup Venture',
            self::JOINT_VENTURE => 'Joint Venture',
            self::NDC_INITIATED => 'NDC-Initiated',
            default => str($categoryKey)->replace('_', ' ')->title()->toString(),
        };
    }

    public static function applyFilter(Builder $query, string $categoryKey): Builder
    {
        return match ($categoryKey) {
            self::TRADITIONAL_EXTERNAL => $query->where(function (Builder $categoryQuery) {
                $categoryQuery->where(function (Builder $traditional) {
                    $traditional->whereIn('origin_track', ['bdg_investment', 'spg_traditional'])
                        ->where('is_svf', false);
                })->orWhere(function (Builder $legacy) {
                    $legacy->whereNull('origin_track')
                        ->whereIn('process_track', ['bdg_investment', 'spg_traditional'])
                        ->where('is_svf', false);
                });
            }),
            self::STARTUP_VENTURE => $query->where('is_svf', true),
            self::JOINT_VENTURE => $query->where(function (Builder $categoryQuery) {
                $categoryQuery->where('origin_track', 'spg_jv')
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('origin_track')->where('process_track', 'spg_jv'));
            }),
            self::NDC_INITIATED => $query->where(function (Builder $categoryQuery) {
                $categoryQuery->where('origin_track', 'spg_ndc_own')
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('origin_track')->where('process_track', 'spg_ndc_own'));
            }),
            default => $query->where(function (Builder $categoryQuery) use ($categoryKey) {
                $categoryQuery->where('origin_track', $categoryKey)
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('origin_track')->where('process_track', $categoryKey));
            }),
        };
    }
}
