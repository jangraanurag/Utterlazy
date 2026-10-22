<?php

use Illuminate\Support\Facades\Schedule;

// WhatsApp placements: daily spend rollover + budget exhaustion (every 5 min)
Schedule::command('wa-placements:manage')->everyFiveMinutes();

// Project outbox → ClickHouse facts (no-op unless enabled)
Schedule::command('analytics:project --limit=200')->everyMinute();

// Retry gateway refunds stuck in refund_pending (every 15 min)
Schedule::command('refunds:retry-pending')->everyFifteenMinutes();

// Refresh the organic search index (no-op unless OPENSEARCH_ENABLED=true)
Schedule::command('search:index-restaurants')->dailyAt('04:00');

// Ops incidents: flag SLA breaches (every minute)
Schedule::command('incidents:sweep')->everyMinute();

// Cutover certification: purge old pricing parity checks (daily 03:45)
Schedule::command('pricing:purge-checks')->dailyAt('03:45');

// Purge idempotency keys older than 24h (hourly)
Schedule::command('idempotency:prune')->hourly();

// Purge published outbox rows older than 7 days (daily)
Schedule::command('outbox:purge-processed')->dailyAt('03:30');

// Expire stale rider assignments every minute
Schedule::command('assignments:expire-stale')->everyMinute();

// Reconcile pending assignments: scan eligible orders, ensure in queue, process (every 30 seconds)
Schedule::command('assignments:reconcile')->everyThirtySeconds();

// Auto-cancel pending orders when vendor doesn't respond (every minute)
Schedule::command('orders:cancel-stale')->everyMinute();

// Ready-with-no-rider: alert at 8 min, cancel + reverse at 15 min (every minute)
Schedule::command('orders:no-rider-sweep')->everyMinute();

// Unreachable customer: 5-minute window, then ticket + abandon + rider attempt pay (every minute)
Schedule::command('orders:unreachable-sweep')->everyMinute();

// Call restaurants with pending orders (every 30 seconds, from 2 min to 5 min after order)
Schedule::command('orders:call-restaurant-reminder')->everyThirtySeconds();

// Call riders with pending assignments (every 30 seconds, after 1 min initial delay)
Schedule::command('orders:call-rider-reminder')->everyThirtySeconds();

// Process pending calls from the queue (every 30 seconds)
Schedule::command('calls:process-queue')->everyThirtySeconds();

// Check for abandoned orders every 5 minutes
Schedule::command('orders:check-abandoned')->everyFiveMinutes();

// Sync operating hours (auto toggle online/offline)
Schedule::command('restaurants:sync-operating-hours')->everyMinute();

// Reset restaurant manual offline status and pauses daily at 6:00 AM
Schedule::command('restaurants:daily-reset')->dailyAt('06:00');

// Settle daily rider payouts at midnight
Schedule::command('payouts:settle-daily')->dailyAt('00:30');

// Process due scheduled orders every minute
Schedule::command('scheduled-orders:process')->everyMinute();

// Cancel scheduled orders for closed restaurants up to 60 minutes ahead
Schedule::command('scheduled-orders:cancel-closed')->everyFiveMinutes();

// Release stale reserved jobs every minute (worker crash recovery)
Schedule::command('queue:release-stale 5')->everyMinute();

// Recover in-flight workflow state after server restart (every minute)
Schedule::command('workflow:recover')->everyMinute();

// Clear expired cache daily
Schedule::command('cache:prune-stale-tags')->daily();

// Process pending rider assignments (every minute — crash recovery)
Schedule::command('riders:process-pending')->everyMinute();

// Auto-generate weekly payout cycles every Wednesday at 08:00 AM
Schedule::command('payouts:generate-weekly-cycles')->wednesdays()->at('08:00');

// Auto-generate weekly payouts every Wednesday at 06:00 AM
Schedule::command('payouts:generate-weekly')->wednesdays()->at('06:00');

// Auto-generate weekly invoices every Tuesday at 08:00 AM
Schedule::command('invoices:generate-weekly')->tuesdays()->at('08:00');

// Manage sponsored ads: activate scheduled, pause exhausted/expired (every 15 minutes)
Schedule::command('ads:manage')->everyFifteenMinutes();

// Expire pending approvals whose scheduled start date has passed
Schedule::command('approvals:expire-stale')->everyFiveMinutes();

// Auto-escalate approval workflow items past their SLA (§5.3)
Schedule::command('approvals:check-sla')->everyFifteenMinutes();

// Aggregate daily rider metrics at 2 AM daily
Schedule::command('rider:aggregate-metrics')->dailyAt('02:00');

// Generate daily rider payout cycles at 8 AM daily
Schedule::command('rider:generate-payouts')->dailyAt('08:00');

// Aggregate daily reporting metrics at 2 AM daily
Schedule::command('metrics:aggregate-daily')->dailyAt('02:00');
