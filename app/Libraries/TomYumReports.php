<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\UserReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: the GMT panel's report queue. A report is open until a staff member takes it, then
 * resolved (with an optional note) or reopened. States live in tomyum_report_states; a report
 * without a row is open.
 */
class TomYumReports
{
    public const OPEN = 'open';
    public const TAKEN = 'taken';
    public const RESOLVED = 'resolved';

    public const VIEWS = [self::OPEN => 'Open', self::TAKEN => 'Being handled', self::RESOLVED => 'Resolved'];

    /** Whether the states table is there (before its migration, every report shows as open). */
    public static function ready(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('tomyum_report_states');
    }

    /** Reports in one view of the queue, newest first, with their states. */
    public static function list(string $view, int $limit = 50)
    {
        $query = UserReport::with(['reporter', 'user'])->orderByDesc('report_id')->limit($limit);
        if (static::ready()) {
            $states = DB::table('tomyum_report_states');
            $query = match ($view) {
                self::TAKEN => $query->whereIn('report_id', (clone $states)->where('status', self::TAKEN)->select('report_id')),
                self::RESOLVED => $query->whereIn('report_id', (clone $states)->where('status', self::RESOLVED)->select('report_id')),
                default => $query->whereNotIn('report_id', (clone $states)->select('report_id')),
            };
        } elseif ($view !== self::OPEN) {
            return collect();
        }

        return static::withStates($query->get());
    }

    /** How many reports are in each view. */
    public static function counts(): array
    {
        $total = UserReport::count();
        if (!static::ready()) {
            return [self::OPEN => $total, self::TAKEN => 0, self::RESOLVED => 0];
        }
        $byStatus = DB::table('tomyum_report_states')->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return [
            self::OPEN => max(0, $total - (int) $byStatus->sum()),
            self::TAKEN => (int) ($byStatus[self::TAKEN] ?? 0),
            self::RESOLVED => (int) ($byStatus[self::RESOLVED] ?? 0),
        ];
    }

    /** Puts each report's state on it (->tomyumState: status, handler_id, note, updated_at). */
    public static function withStates($reports)
    {
        $states = static::ready() && count($reports) > 0
            ? DB::table('tomyum_report_states')->whereIn('report_id', $reports->pluck('report_id'))->get()->keyBy('report_id')
            : collect();
        foreach ($reports as $report) {
            $report->tomyumState = $states[$report->getKey()] ?? null;
        }

        return $reports;
    }

    public static function set(int $reportId, string $status, ?int $handlerId, ?string $note = null): void
    {
        if ($status === self::OPEN) {
            DB::table('tomyum_report_states')->where('report_id', $reportId)->delete();

            return;
        }
        DB::table('tomyum_report_states')->updateOrInsert(
            ['report_id' => $reportId],
            ['status' => $status, 'handler_id' => $handlerId, 'note' => $note, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /** Where the reported thing is, for a "view" link (null when there's nothing to open). */
    public static function link(UserReport $report): ?string
    {
        $id = $report->reportable_id;

        return match (true) {
            $report->reportable_type === 'user' => route('panel.gmt.users.show', ['user' => $report->user_id]),
            $report->reportable_type === 'beatmapset' && $id > 0 => route('beatmapsets.show', ['beatmapset' => $id]),
            $report->reportable_type === 'solo_score' && $id > 0 => route('scores.show', ['score' => $id]),
            $report->reportable_type === 'comment' && $id > 0 => route('comments.show', ['comment' => $id]),
            $report->reportable_type === 'forum_post' && $id > 0 => route('forum.posts.show', ['post' => $id]),
            $report->reportable_type === 'message' => route('panel.gmt.chat', ['user' => $report->user_id]),
            default => null,
        };
    }
}
