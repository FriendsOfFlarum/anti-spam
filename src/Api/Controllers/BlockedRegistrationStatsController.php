<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api\Controllers;

use Carbon\Carbon;
use DateTime;
use Flarum\Extension\ExtensionManager;
use Flarum\Http\RequestUtil;
use FoF\AntiSpam\Model\BlockedRegistration;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * How the forum is holding up against spam, for the dashboard widget.
 *
 * The extension defends on three fronts, and a useful answer covers all of them: registrations
 * turned away at the door, posts the content filter catches from accounts that got in, and
 * users a moderator later marks as spammers.
 *
 * Only registration blocks are stored by this extension. The other two are read from the audit
 * log — actions this extension registers itself — because that is the only durable record:
 * flarum/flags deletes a flag row when it is dismissed and when its post is deleted, so that
 * table can only answer what is currently awaiting review, which is reported separately.
 *
 * Served here rather than through flarum/statistics because that extension's entity list is a
 * hardcoded private array with no extender, so an extension cannot contribute to it.
 */
class BlockedRegistrationStatsController implements RequestHandlerInterface
{
    /**
     * Long enough to spare the database on a dashboard that several admins may sit on, short
     * enough that a spam wave shows up while it is still happening.
     */
    public static int $cacheTtl = 300;

    public function __construct(
        protected CacheRepository $cache,
        protected ExtensionManager $extensions,
        protected ConnectionInterface $db
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Statistics are only ever shown in the admin panel, and blocked registrations carry
        // email addresses and IPs; the read permission is not enough on its own.
        RequestUtil::getActor($request)->assertAdmin();

        $query = $request->getQueryParams();

        if (Arr::get($query, 'period') === 'lifetime') {
            return new JsonResponse($this->lifetime());
        }

        return new JsonResponse($this->timed());
    }

    /**
     * @return array<string, mixed>
     */
    private function lifetime(): array
    {
        return $this->cache->remember('fof-anti-spam.stats.lifetime', self::$cacheTtl, function (): array {
            $week = Carbon::now()->subWeek();
            $previousWeek = Carbon::now()->subWeeks(2);

            $stats = [
                'registrationsBlocked' => $this->measure(
                    fn () => BlockedRegistration::query(),
                    'attempted_at',
                    $week,
                    $previousWeek
                ),
                'byReason' => $this->countsByReason(),
                'byProvider' => $this->countsByProvider(),
            ];

            // Spam flags still open, from the flags table itself. This is the moderator's
            // queue, not a tally of work done: flarum/flags deletes a flag when it is
            // dismissed, and again when the post is deleted — which is what marking the author
            // as a spammer does. It is labelled accordingly, and carries no trend, because a
            // falling number here means moderation is working rather than spam abating.
            $stats['flagsAwaitingReview'] = $this->db->table('flags')->where('type', 'spam')->count();

            // The durable counts live in the audit log, which does not delete. Both are
            // actions this extension registers itself, so they are only available when
            // flarum/audit is enabled.
            if ($this->extensions->isEnabled('flarum-audit')) {
                $stats['usersMarkedAsSpammers'] = $this->measure(
                    fn () => $this->db->table('audit_log')->where('action', 'user.marked_as_spammer'),
                    'created_at',
                    $week,
                    $previousWeek
                );

                $stats['postsFlagged'] = $this->measure(
                    fn () => $this->db->table('audit_log')->where('action', 'post.flagged_as_spam'),
                    'created_at',
                    $week,
                    $previousWeek
                );
            }

            return $stats;
        });
    }

    /**
     * A metric's total, its last seven days, and the seven days before that.
     *
     * The previous period is what makes the number mean anything: 87 blocks this week is only
     * good or bad news next to what last week looked like.
     *
     * @param callable(): (\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>) $query
     * @return array{total: int, period: int, previousPeriod: int}
     */
    private function measure(callable $query, string $column, Carbon $since, Carbon $previousSince): array
    {
        return [
            'total' => $query()->count(),
            'period' => $query()->where($column, '>=', $since)->count(),
            'previousPeriod' => $query()
                ->where($column, '>=', $previousSince)
                ->where($column, '<', $since)
                ->count(),
        ];
    }

    /**
     * Counts keyed by unix timestamp, bucketed by hour for the last day and by day before that
     * — the same shape flarum/statistics produces, so the chart code is conventional.
     *
     * @return array<int, int>
     */
    private function timed(): array
    {
        return $this->cache->remember('fof-anti-spam.stats.timed', self::$cacheTtl, function (): array {
            return $this->timedCounts(new DateTime('-2 years'), new DateTime());
        });
    }

    /**
     * @return array<int, int>
     */
    private function timedCounts(DateTime $start, DateTime $end): array
    {
        $query = BlockedRegistration::query();
        $driver = $query->getConnection()->getDriverName();

        $formats = match ($driver) {
            'pgsql' => ['YYYY-MM-DD HH24:00:00', 'YYYY-MM-DD'],
            default => ['%Y-%m-%d %H:00:00', '%Y-%m-%d'],
        };

        $format = "CASE WHEN attempted_at > ? THEN '$formats[0]' ELSE '$formats[1]' END";

        $grouped = match ($driver) {
            'sqlite' => "strftime($format, attempted_at)",
            'pgsql' => "TO_CHAR(attempted_at, $format)",
            'mysql', 'mariadb' => "DATE_FORMAT(attempted_at, $format)",
            // Rather than guess at another dialect's date functions, fall back to counting in
            // PHP. Slower, but correct, and this table is small next to posts or users.
            default => null,
        };

        if ($grouped === null) {
            return $this->timedCountsInPhp($start, $end);
        }

        $results = $query
            ->selectRaw($grouped.' as time_group', [new DateTime('-25 hours')])
            ->selectRaw('COUNT(id) as count')
            ->where('attempted_at', '>', $start)
            ->where('attempted_at', '<=', $end)
            ->groupBy('time_group')
            ->pluck('count', 'time_group');

        $timed = [];

        foreach ($results as $time => $count) {
            $timed[(new DateTime((string) $time))->getTimestamp()] = (int) $count;
        }

        return $timed;
    }

    /**
     * @return array<int, int>
     */
    private function timedCountsInPhp(DateTime $start, DateTime $end): array
    {
        $cutoff = new DateTime('-25 hours');
        $timed = [];

        BlockedRegistration::query()
            ->where('attempted_at', '>', $start)
            ->where('attempted_at', '<=', $end)
            ->orderBy('attempted_at')
            ->each(function (BlockedRegistration $blocked) use (&$timed, $cutoff): void {
                $at = $blocked->attempted_at;

                if ($at === null) {
                    return;
                }

                $bucket = $at > $cutoff
                    ? $at->copy()->startOfHour()
                    : $at->copy()->startOfDay();

                $key = $bucket->getTimestamp();
                $timed[$key] = ($timed[$key] ?? 0) + 1;
            });

        return $timed;
    }

    /**
     * How often each rule fired. Read from the recorded reasons rather than re-derived from the
     * StopForumSpam response, so it reflects the decisions actually taken; rows predating that
     * column are counted separately rather than guessed at.
     *
     * @return array<string, int>
     */
    private function countsByReason(): array
    {
        $counts = [];
        $unrecorded = 0;

        BlockedRegistration::query()
            ->select(['reasons'])
            ->each(function (BlockedRegistration $blocked) use (&$counts, &$unrecorded): void {
                if ($blocked->reasons === null) {
                    $unrecorded++;

                    return;
                }

                $decoded = json_decode($blocked->reasons, true);

                foreach (Arr::get($decoded, 'reasons', []) as $reason) {
                    $counts[$reason] = ($counts[$reason] ?? 0) + 1;
                }
            });

        arsort($counts);

        if ($unrecorded > 0) {
            $counts['unrecorded'] = $unrecorded;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function countsByProvider(): array
    {
        return BlockedRegistration::query()
            ->selectRaw('COALESCE(provider, ?) as provider_name, COUNT(id) as count', ['unknown'])
            ->groupBy('provider_name')
            ->orderByDesc('count')
            ->pluck('count', 'provider_name')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
