<?php

/**
 * Wave 1 smoke — outbox, idempotency, placements, roles.
 * No full Laravel boot needed: Eloquent capsule + sqlite in-memory.
 *
 * Run: php tests/wave1_smoke.php
 */

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$capsule = new Capsule;
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule->getDatabaseManager()->setDefaultConnection($capsule->getConnection()->getName());

$schema = $capsule->schema();

$schema->create('outbox', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('event_id')->unique();
    $t->string('aggregate_type', 50);
    $t->uuid('aggregate_id');
    $t->string('event_type', 80);
    $t->json('payload');
    $t->uuid('city_id')->nullable();
    $t->timestamp('occurred_at')->nullable();
    $t->timestamp('published_at')->nullable();
    $t->unsignedSmallInteger('attempts')->default(0);
    $t->text('last_error')->nullable();
});

$schema->create('processed_events', function (Blueprint $t) {
    $t->uuid('event_id');
    $t->string('consumer', 80);
    $t->timestamp('processed_at')->nullable();
    $t->primary(['event_id', 'consumer']);
});

$schema->create('idempotency_keys', function (Blueprint $t) {
    $t->string('key', 80);
    $t->uuid('user_id');
    $t->uuid('order_id')->nullable();
    $t->json('response');
    $t->timestamp('created_at')->nullable();
    $t->primary(['key', 'user_id']);
});

$schema->create('wa_placements', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('restaurant_id');
    $t->uuid('city_id');
    $t->decimal('daily_budget', 12, 2);
    $t->decimal('cpc_bid', 12, 2);
    $t->decimal('spent_today', 12, 2)->default(0);
    $t->date('spent_date')->nullable();
    $t->timestamp('start_at');
    $t->timestamp('end_at');
    $t->string('creative_title', 40);
    $t->string('creative_body', 120);
    $t->string('status', 20)->default('draft');
    $t->decimal('pinned_floor', 12, 2)->nullable();
    $t->uuid('approval_instance_id')->nullable();
    $t->string('rejection_reason', 300)->nullable();
    $t->timestamps();
});

$schema->create('users', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->string('name')->nullable();
    $t->string('email')->nullable();
    $t->string('phone')->nullable();
    $t->string('password')->nullable();
    $t->boolean('is_admin')->default(false);
    $t->string('admin_role')->nullable();
    $t->decimal('wallet_balance', 12, 2)->default(0);
    $t->integer('khaalo_coins')->default(0);
    $t->timestamps();
});

$schema->create('wallet_transactions', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('user_id');
    $t->decimal('amount', 12, 2);
    $t->string('type', 20);
    $t->string('description')->nullable();
    $t->timestamps();
});

$schema->create('orders', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('user_id')->nullable();
    $t->uuid('restaurant_id')->nullable();
    $t->uuid('city_id')->nullable();
    $t->string('status', 30)->default('pending');
    $t->string('channel', 12)->default('app');
    $t->string('cancel_reason', 40)->nullable();
    $t->string('cancel_note', 255)->nullable();
    $t->string('payment_mode', 20)->default('cod');
    $t->string('payment_status', 20)->default('pending');
    $t->unsignedSmallInteger('refund_attempts')->default(0);
    $t->text('refund_last_error')->nullable();
    $t->timestamp('refund_retried_at')->nullable();
    $t->decimal('grand_total', 12, 2)->default(0);
    $t->decimal('subtotal', 12, 2)->default(0);
    $t->string('razorpay_payment_id')->nullable();
    $t->timestamp('customer_unreachable_at')->nullable();
    $t->timestamps();
});

$schema->create('cities', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->string('name');
    $t->string('state')->nullable();
    $t->boolean('is_active')->default(true);
    $t->timestamps();
});

$schema->create('org_units', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->string('department')->nullable();
    $t->string('unit_type')->nullable();
    $t->string('name');
    $t->uuid('parent_org_unit_id')->nullable();
    $t->json('geography_scope')->nullable();
    $t->timestamps();
});

$schema->create('org_roles', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('org_unit_id')->nullable();
    $t->string('title')->nullable();
    $t->uuid('assigned_user_id')->nullable();
    $t->uuid('backup_user_id')->nullable();
    $t->boolean('is_active')->default(true);
    $t->timestamps();
});

$schema->create('projected_orders', function (Blueprint $t) {
    $t->string('order_id', 36)->primary();
    $t->uuid('user_id')->nullable();
    $t->uuid('restaurant_id')->nullable();
    $t->uuid('city_id')->nullable();
    $t->string('channel', 12)->default('app');
    $t->string('status', 30);
    $t->string('cancel_reason', 40)->nullable();
    $t->decimal('grand_total', 12, 2)->default(0);
    $t->decimal('tax', 12, 2)->default(0);
    $t->decimal('vendor_discount', 12, 2)->default(0);
    $t->decimal('delivery_fee', 12, 2)->default(0);
    $t->decimal('platform_fee', 12, 2)->default(0);
    $t->decimal('coupon_discount', 12, 2)->default(0);
    $t->decimal('commission', 12, 2)->default(0);
    $t->decimal('commission_gst', 12, 2)->default(0);
    $t->decimal('net_payout', 12, 2)->default(0);
    $t->json('pricing')->nullable();
    $t->unsignedBigInteger('version')->default(0);
    $t->string('event_type', 80)->nullable();
    $t->string('last_event_id', 36)->nullable();
    $t->timestamp('last_event_at')->nullable();
    $t->timestamp('projected_at')->nullable();
});

$schema->create('pii_retention_runs', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('actor_user_id')->nullable();
    $t->timestamp('cutoff');
    $t->boolean('dry_run')->default(true);
    $t->unsignedInteger('rows_deleted')->default(0);
    $t->json('breakdown')->nullable();
    $t->timestamp('created_at')->nullable();
});

$schema->create('otps', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->string('phone');
    $t->string('code');
    $t->timestamp('expires_at');
    $t->boolean('verified')->default(false);
    $t->unsignedSmallInteger('attempts')->default(0);
    $t->timestamps();
});

$schema->create('personal_access_tokens', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('tokenable_type');
    $t->uuid('tokenable_id');
    $t->string('name');
    $t->string('token', 64)->unique();
    $t->text('abilities')->nullable();
    $t->timestamp('last_used_at')->nullable();
    $t->timestamp('expires_at')->nullable();
    $t->timestamps();
});

$schema->create('riders', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->string('name')->nullable();
    $t->string('phone')->nullable();
    $t->string('email')->nullable();
    $t->string('password')->nullable();
    $t->boolean('is_online')->default(false);
    $t->boolean('is_verified')->default(false);
    $t->timestamps();
});

$schema->create('sos_alerts', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('rider_id');
    $t->uuid('order_id')->nullable();
    $t->decimal('latitude', 10, 7)->nullable();
    $t->decimal('longitude', 10, 7)->nullable();
    $t->string('status', 20)->default('open');
    $t->uuid('incident_id')->nullable();
    $t->uuid('acknowledged_by')->nullable();
    $t->timestamp('acknowledged_at')->nullable();
    $t->timestamp('resolved_at')->nullable();
    $t->string('resolution_note', 500)->nullable();
    $t->json('metadata')->nullable();
    $t->timestamp('triggered_at')->nullable();
    $t->timestamps();
});

$schema->create('wa_workforce', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('user_id');
    $t->uuid('city_id')->nullable();
    $t->time('shift_start')->default('09:00');
    $t->time('shift_end')->default('18:00');
    $t->unsignedSmallInteger('queue_cap')->default(10);
    $t->boolean('active')->default(true);
    $t->timestamps();
});

$schema->create('ops_shifts', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('user_id');
    $t->uuid('org_unit_id')->nullable();
    $t->uuid('city_id')->nullable();
    $t->string('role', 40)->nullable();
    $t->timestamp('starts_at');
    $t->timestamp('ends_at');
    $t->string('status', 20)->default('scheduled');
    $t->string('notes', 200)->nullable();
    $t->string('cancelled_reason', 200)->nullable();
    $t->uuid('created_by')->nullable();
    $t->timestamps();
});

$schema->create('pii_access_logs', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('actor_user_id');
    $t->string('actor_role', 40)->nullable();
    $t->string('action', 20);
    $t->string('resource_type', 40);
    $t->uuid('resource_id')->nullable();
    $t->json('filters')->nullable();
    $t->unsignedInteger('row_count')->nullable();
    $t->string('purpose', 200)->nullable();
    $t->string('ip_address', 45)->nullable();
    $t->string('user_agent', 255)->nullable();
    $t->timestamp('created_at')->nullable();
});

$schema->create('ops_incidents', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('city_id')->nullable();
    $t->string('severity', 8);
    $t->string('category', 30);
    $t->string('title', 160);
    $t->text('description')->nullable();
    $t->string('status', 20)->default('open');
    $t->unsignedTinyInteger('escalation_level')->default(0);
    $t->uuid('owner_user_id')->nullable();
    $t->uuid('opened_by')->nullable();
    $t->timestamp('opened_at')->nullable();
    $t->timestamp('ack_at')->nullable();
    $t->timestamp('resolved_at')->nullable();
    $t->timestamp('sla_due_at')->nullable();
    $t->timestamp('sla_breached_at')->nullable();
    $t->timestamp('last_escalated_at')->nullable();
    $t->string('resolution_note', 500)->nullable();
    $t->timestamps();
});

$schema->create('analytics_projected_events', function (Blueprint $t) {
    $t->uuid('event_id')->primary();
    $t->string('table_name', 40);
    $t->timestamp('projected_at')->nullable();
});

$schema->create('restaurants', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->string('name');
    $t->string('logo_url')->nullable();
    $t->decimal('rating', 3, 2)->nullable();
    $t->uuid('city_id')->nullable();
    $t->unsignedInteger('total_orders_completed')->default(0);
    $t->boolean('is_active')->default(true);
    $t->timestamps();
});

$schema->create('wa_city_floors', function (Blueprint $t) {
    $t->uuid('city_id')->primary();
    $t->decimal('floor_cpc', 12, 2)->default(5);
    $t->unsignedSmallInteger('max_slots')->default(5);
    $t->uuid('updated_by')->nullable();
    $t->timestamps();
});

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label} — {$detail}\n";
    }
}

use App\Models\Outbox;
use App\Models\WaPlacement;
use App\Services\OutboxPublisher;
use Illuminate\Support\Facades\DB;

echo "=== Outbox enqueue + topic map ===\n";

$publisher = new OutboxPublisher;
$uuid = fn () => (string) \Illuminate\Support\Str::uuid();

$row = $publisher->enqueue('order', $uuid(), 'order.created', ['order_id' => $uuid(), 'status' => 'pending'], null);
check('enqueue writes a row', $row->exists && $row->event_id !== null);
check('payload stored as array', is_array($row->payload) && $row->payload['status'] === 'pending');
check('topic map: order → order.events.v1', $publisher->topicFor('order') === 'order.events.v1');
check('topic map: payment → payment.events.v1', $publisher->topicFor('payment') === 'payment.events.v1');
check('topic map: wa_placement → ads.placement.v1', $publisher->topicFor('wa_placement') === 'ads.placement.v1');
check('topic map: unknown fallback', $publisher->topicFor('support_ticket') === 'support.ticket.v1');

echo "=== Idempotency (in-transaction guard semantics) ===\n";

$userId = $uuid();
$key = 'wa-test-key-12345678';

$inserted1 = Capsule::table('idempotency_keys')->insertOrIgnore([
    'key' => $key, 'user_id' => $userId, 'order_id' => null,
    'response' => json_encode(['code' => 409, 'body' => ['error' => 'in progress'], 'pending' => true]),
    'created_at' => now(),
]);
check('first idempotency insert succeeds', $inserted1 === 1);

$inserted2 = Capsule::table('idempotency_keys')->insertOrIgnore([
    'key' => $key, 'user_id' => $userId, 'order_id' => null,
    'response' => json_encode(['code' => 409, 'body' => ['error' => 'in progress'], 'pending' => true]),
    'created_at' => now(),
]);
check('duplicate idempotency insert is ignored', $inserted2 === 0);

$row = Capsule::table('idempotency_keys')
    ->where('key', $key)->where('user_id', $userId)->first();
check('stored response is the FIRST write', $row !== null && str_contains($row->response, 'in progress'));

echo "=== WaPlacement runnable rules ===\n";

$now = \Carbon\Carbon::now();
$placement = WaPlacement::create([
    'restaurant_id' => $uuid(),
    'city_id' => $uuid(),
    'daily_budget' => 1000,
    'cpc_bid' => 6,
    'start_at' => $now->copy()->subHour(),
    'end_at' => $now->copy()->addDay(),
    'creative_title' => 'Test Kitchen',
    'creative_body' => 'Biryani at your door',
    'status' => WaPlacement::STATUS_ACTIVE,
]);
check('active + in-window + budget → runnable', $placement->isRunnable() === true);

$placement->update(['spent_today' => 1000]);
check('budget exhausted → not runnable', $placement->fresh()->isRunnable() === false);

$placement->update(['spent_today' => 0, 'status' => WaPlacement::STATUS_PENDING_REVIEW]);
check('pending review → not runnable', $placement->fresh()->isRunnable() === false);

$placement->update(['status' => WaPlacement::STATUS_ACTIVE, 'start_at' => $now->copy()->addDay()]);
check('future start → not runnable', $placement->fresh()->isRunnable() === false);

echo "=== WhatsApp reco mix (paid preferred, unpaid visible) ===\n";

$reco = new \App\Services\WaRecoService;
$cityId = $uuid();

$paid = WaPlacement::create([
    'restaurant_id' => $uuid(),
    'city_id' => $cityId,
    'daily_budget' => 500,
    'cpc_bid' => 7,
    'spent_today' => 0,
    'spent_date' => now()->toDateString(),
    'start_at' => now()->subHour(),
    'end_at' => now()->addDay(),
    'creative_title' => 'Paid Kitchen',
    'creative_body' => 'Hot meals delivered fast',
    'status' => WaPlacement::STATUS_ACTIVE,
    'pinned_floor' => 5,
]);

WaPlacement::create([
    'restaurant_id' => $uuid(),
    'city_id' => $cityId,
    'daily_budget' => 500,
    'cpc_bid' => 99,
    'start_at' => now()->subHour(),
    'end_at' => now()->addDay(),
    'creative_title' => 'Unpaid Kitchen',
    'creative_body' => 'Never pays for placement',
    'status' => WaPlacement::STATUS_DRAFT,
]);

$organicRestaurant = \App\Models\Restaurant::create([
    'name' => 'Organic Kitchen',
    'city_id' => $cityId,
    'is_active' => true,
    'total_orders_completed' => 100,
]);

$inventory = $reco->recommendedInventory($cityId, 5);
check('reco includes paid placement', count($inventory) === 2, json_encode($inventory));
check('reco paid item is sponsored', ($inventory[0]['sponsored'] ?? false) === true);
check('reco includes unpaid restaurant', collect($inventory)->contains(
    fn ($item) => $item['restaurant_id'] === $organicRestaurant->id && $item['sponsored'] === false
));
check('reco paid appears before organic', $inventory[0]['restaurant_id'] === $paid->restaurant_id);

// Bid below pinned floor is ineligible even when active (organic still visible).
$paid->update(['cpc_bid' => 4]);
$belowFloor = $reco->recommendedInventory($cityId, 5);
check('bid below pinned floor excluded', collect($belowFloor)->pluck('placement_id')->doesntContain($paid->id));
check('organic still visible when no paid inventory', collect($belowFloor)->contains(
    fn ($item) => $item['restaurant_id'] === $organicRestaurant->id
));
$paid->update(['cpc_bid' => 7, 'spent_today' => 500]);
check('budget-exhausted placement excluded', collect($reco->recommendedInventory($cityId, 5))
    ->pluck('placement_id')->doesntContain($paid->id));

echo "=== AdminAuth role table ===\n";

require __DIR__.'/../app/Http/Middleware/AdminAuth.php';

$roles = \App\Http\Middleware\AdminAuth::roles();
$expected = ['super_admin', 'city_manager', 'support_agent', 'finance_manager', 'account_manager',
    'support_l1', 'support_l2', 'support_lead', 'city_ops', 'rider_ops', 'catalog_ops',
    'onboarding_agent', 'ads_ops', 'whatsapp_ops', 'fraud_analyst', 'growth', 'auditor', ];
foreach ($expected as $role) {
    check("role present: {$role}", in_array($role, $roles, true));
}

$perms = \App\Http\Middleware\AdminAuth::ROLE_PERMISSIONS;
$supportL1 = $perms['support_l1'];
$adsOps = $perms['ads_ops'];
$auditor = $perms['auditor'];

check('support_l1 cannot approve placements', ! in_array('placements.review', $supportL1, true));
check('support_l1 cannot originate refunds', ! in_array('complaints.refund_originate', $supportL1, true));
check('ads_ops can review placements', in_array('placements.review', $adsOps, true));
check('ads_ops can manage floors', in_array('floors.manage', $adsOps, true));
check('auditor has no write permissions', collect($auditor)->every(fn ($p) => str_ends_with($p, '.view')));
check('super_admin still has wildcard (money bypass is blocked in approval engine, not RBAC)', in_array('*', $perms['super_admin'], true));

echo "=== Outbox publisher semantics (restart / retry / DLQ) ===\n";

class FakeTransport implements \App\Services\Outbox\OutboxTransport
{
    public array $published = [];
    public array $dlq = [];
    public bool $fail = false;

    public function publish(string $topic, ?string $partitionKey, array $envelope): void
    {
        if ($this->fail) {
            throw new \RuntimeException('broker unavailable');
        }
        $this->published[] = ['topic' => $topic, 'event_id' => $envelope['event_id']];
    }

    public function publishToDlq(string $topic, array $envelope): void
    {
        $this->dlq[] = ['topic' => $topic, 'event_id' => $envelope['event_id']];
    }
}

$fake = new FakeTransport;
$publisher->useTransport($fake);

// Drain any rows created earlier in this suite.
$publisher->publishPending(100, 5);

$okRow = $publisher->enqueue('order', $uuid(), 'order.created', ['order_id' => $uuid()], null);
$result = $publisher->publishPending(100, 5);
check('publisher publishes pending row', $result['published'] === 1 && $okRow->fresh()->published_at !== null);
check('publisher does not republish', $publisher->publishPending(100, 5)['published'] === 0);

$failRow = $publisher->enqueue('payment', $uuid(), 'payment.status_changed', ['order_id' => $uuid()], null);
$fake->fail = true;

$publisher->publishPending(100, 2);
check('failed publish stays pending with attempt 1', $failRow->fresh()->published_at === null && $failRow->fresh()->attempts === 1);

$publisher->publishPending(100, 2);
check('second failure reaches max and sends DLQ', count($fake->dlq) === 1 && $failRow->fresh()->attempts === 2);

$before = count($fake->dlq);
$publisher->publishPending(100, 2);
check('exhausted row is not retried again', count($fake->dlq) === $before);

$fake->fail = false;

echo "=== Reversal: wallet credit, idempotent, COD untouched ===\n";

use App\Models\Order;
use App\Models\User;
use App\Services\OrderReversalService;
use App\Services\Refunds\RefundGateway;

$buyer = User::create(['wallet_balance' => 0]);
$walletOrder = Order::create([
    'user_id' => $buyer->id,
    'status' => 'cancelled',
    'payment_mode' => 'wallet',
    'payment_status' => 'paid',
    'grand_total' => 500,
    'subtotal' => 450,
]);

check('wallet order reverses', OrderReversalService::reverse($walletOrder, 'restaurant_timeout') === true);
check('wallet credited exactly once', (float) $buyer->fresh()->wallet_balance === 500.0);
check('payment marked refunded', $walletOrder->fresh()->payment_status === 'refunded');
check('cancel reason stamped', $walletOrder->fresh()->cancel_reason === 'restaurant_timeout');

check('second reverse is a no-op', OrderReversalService::reverse($walletOrder->fresh(), 'restaurant_timeout') === false);
check('wallet not double-credited', (float) $buyer->fresh()->wallet_balance === 500.0);

$codOrder = Order::create([
    'user_id' => $buyer->id,
    'status' => 'cancelled',
    'payment_mode' => 'cod',
    'payment_status' => 'pending',
    'grand_total' => 300,
    'subtotal' => 280,
]);

check('COD order reversal does not credit wallet', OrderReversalService::reverse($codOrder, 'no_rider') === false);
check('COD wallet unchanged', (float) $buyer->fresh()->wallet_balance === 500.0);

echo "=== Shadow status mapping ===\n";

use App\Support\OrderStatusMapper;

check('map: pending → PENDING', OrderStatusMapper::toCommand('pending') === 'PENDING');
check('map: ready_for_pickup → READY_FOR_PICKUP', OrderStatusMapper::toCommand('ready_for_pickup') === 'READY_FOR_PICKUP');
check('map: unknown → null', OrderStatusMapper::toCommand('teleported') === null);
check('path: preparing needs confirm first', OrderStatusMapper::pathTo('PREPARING') === ['CONFIRMED', 'PREPARING']);
check('path: delivered is full chain', OrderStatusMapper::pathTo('DELIVERED') === ['CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP', 'OUT_FOR_DELIVERY', 'DELIVERED']);

class FakeRefundGateway implements RefundGateway
{
    public int $calls = 0;
    public bool $fail = true;

    public function refund(Order $order, float $amount, array $notes = []): void
    {
        $this->calls++;

        if ($this->fail) {
            throw new \RuntimeException('gateway_down');
        }
    }
}

echo "=== Refund retry (gateway failure -> pending -> recovered) ===\n";


$gw = new FakeRefundGateway();

$prepaid = Order::create([
    'user_id' => $buyer->id,
    'status' => 'cancelled',
    'payment_mode' => 'prepaid',
    'payment_status' => 'paid',
    'razorpay_payment_id' => 'pay_test_1',
    'grand_total' => 300,
    'subtotal' => 280,
]);

check('prepaid reverse fails gracefully', OrderReversalService::reverse($prepaid, 'no_rider', $gw) === false);
check('status refund_pending', $prepaid->fresh()->payment_status === 'refund_pending');
check('attempt counter incremented', (int) $prepaid->fresh()->refund_attempts === 1);
check('gateway error stored', str_contains((string) $prepaid->fresh()->refund_last_error, 'gateway_down'));

$gw->fail = false;
check('retry succeeds', OrderReversalService::retryPending($prepaid->fresh(), $gw) === true);
check('status refunded after retry', $prepaid->fresh()->payment_status === 'refunded');
check('error cleared after retry', $prepaid->fresh()->refund_last_error === null);
check('second retry is a no-op', OrderReversalService::retryPending($prepaid->fresh(), $gw) === false);
check('no extra gateway call on no-op', $gw->calls === 2);

$exhausted = Order::create([
    'user_id' => $buyer->id,
    'status' => 'cancelled',
    'payment_mode' => 'prepaid',
    'payment_status' => 'refund_pending',
    'razorpay_payment_id' => 'pay_test_2',
    'grand_total' => 100,
    'subtotal' => 90,
    'refund_attempts' => OrderReversalService::MAX_RETRY_ATTEMPTS,
]);

$callsBefore = $gw->calls;
check('retry at cap refused', OrderReversalService::retryPending($exhausted, $gw) === false);
check('no gateway call at cap', $gw->calls === $callsBefore);

echo "=== Analytics projector (ClickHouse facts, idempotent) ===\n";

use App\Services\Analytics\AnalyticsProjector;
use App\Services\Analytics\AnalyticsSink;

class FakeAnalyticsSink implements AnalyticsSink
{
    public array $rows = [];
    public bool $fail = false;

    public function insert(string $table, array $rows): void
    {
        if ($this->fail) {
            throw new \RuntimeException('clickhouse_down');
        }

        foreach ($rows as $row) {
            $this->rows[] = ['table' => $table, 'row' => $row];
        }
    }

    public function only(string $table): array
    {
        return array_values(array_map(
            fn ($e) => $e['row'],
            array_filter($this->rows, fn ($e) => $e['table'] === $table),
        ));
    }
}

Capsule::table('analytics_projected_events')->delete();
Capsule::table('outbox')->delete();

$orderPayload = [
    'order_id' => (string) $uuid(),
    'user_id' => (string) $uuid(),
    'restaurant_id' => (string) $uuid(),
    'city_id' => (string) $uuid(),
    'status' => 'pending',
    'channel' => 'whatsapp',
    'payment_mode' => 'prepaid',
    'grand_total' => 199,
];
$publisher->enqueue('order', $orderPayload['order_id'], 'order.created', $orderPayload, $orderPayload['city_id']);

$paymentPayload = [
    'order_id' => $orderPayload['order_id'],
    'user_id' => $orderPayload['user_id'],
    'restaurant_id' => $orderPayload['restaurant_id'],
    'payment_status' => 'paid',
    'payment_mode' => 'prepaid',
    'grand_total' => 199,
];
$publisher->enqueue('payment', $orderPayload['order_id'], 'payment.status_changed', $paymentPayload, $orderPayload['city_id']);

$adPayload = [
    'placement_id' => (string) $uuid(),
    'restaurant_id' => (string) $uuid(),
    'city_id' => (string) $uuid(),
    'amount' => 7,
];
$publisher->enqueue('wa_placement', $adPayload['placement_id'], 'wa_placement.click', $adPayload, $adPayload['city_id']);

$sink = new FakeAnalyticsSink();
$projector = new AnalyticsProjector($sink, true, $capsule->getDatabaseManager());

$result = $projector->project(100);
check('projector: 3 events projected', $result['projected'] === 3 && $result['failed'] === 0);

$orderRows = $sink->only('order_events');
check('projector: order row written', count($orderRows) === 1);
check('projector: order city carried', ($orderRows[0]['city_id'] ?? null) === $orderPayload['city_id']);
check('projector: order total as decimal string', ($orderRows[0]['grand_total'] ?? null) === '199.00');
check('projector: channel carried', ($orderRows[0]['channel'] ?? null) === 'whatsapp');

$paymentRows = $sink->only('payment_events');
check('projector: payment row written', count($paymentRows) === 1 && ($paymentRows[0]['payment_status'] ?? null) === 'paid');

$adRows = $sink->only('ad_events');
check('projector: ad click row written', count($adRows) === 1 && ($adRows[0]['amount'] ?? null) === '7.00');

check('projector: replay writes nothing', $projector->project(100)['projected'] === 0);

$failPayload = array_merge($orderPayload, ['status' => 'preparing', 'from_status' => 'pending']);
$failEvent = $publisher->enqueue('order', $orderPayload['order_id'], 'order.status_changed', $failPayload, $orderPayload['city_id']);

$sink->fail = true;
$failedRun = $projector->project(100);
check('projector: sink failure is reported', $failedRun['failed'] === 1 && $failedRun['projected'] === 0);
check('projector: failed event not marked', ! Capsule::table('analytics_projected_events')->where('event_id', $failEvent->event_id)->exists());

$sink->fail = false;
$retryRun = $projector->project(100);
check('projector: failed event retries', $retryRun['projected'] === 1);
check('projector: failed event now marked', Capsule::table('analytics_projected_events')->where('event_id', $failEvent->event_id)->exists());

$disabled = new AnalyticsProjector(new FakeAnalyticsSink(), false, $capsule->getDatabaseManager());
check('projector: disabled is a no-op', $disabled->project(10)['disabled'] === true);

echo "=== City analytics readers (ClickHouse + MySQL fallback) ===\n";

use App\Services\Analytics\ClickHouseCityAnalytics;
use App\Services\Analytics\ClickHouseQuery;
use App\Services\Analytics\FallbackCityAnalytics;
use App\Services\Analytics\MySqlCityAnalytics;

$cityA = $uuid();
$cityB = $uuid();

$mkOrder = function (string $city, string $status, float $total, ?string $reason = null) use ($buyer) {
    return Order::create([
        'user_id' => $buyer->id,
        'city_id' => $city,
        'status' => $status,
        'cancel_reason' => $reason,
        'payment_mode' => 'cod',
        'payment_status' => 'pending',
        'grand_total' => $total,
        'subtotal' => $total,
    ]);
};

$mkOrder($cityA, 'delivered', 500);
$mkOrder($cityA, 'cancelled', 300, 'restaurant_timeout');
$mkOrder($cityA, 'pending', 200);
$mkOrder($cityB, 'delivered', 100);

$mysqlReader = new MySqlCityAnalytics();
$mysqlRows = $mysqlReader->daily([$cityA, $cityB]);

check('mysql daily: order count', ($mysqlRows[$cityA]['orders'] ?? 0) === 3);
check('mysql daily: delivered count', ($mysqlRows[$cityA]['delivered'] ?? 0) === 1);
check('mysql daily: cancelled count', ($mysqlRows[$cityA]['cancelled'] ?? 0) === 1);
check('mysql daily: gmv', ($mysqlRows[$cityA]['gmv'] ?? 0) === 1000.0);
check('mysql daily: second city', ($mysqlRows[$cityB]['orders'] ?? 0) === 1 && ($mysqlRows[$cityB]['delivered'] ?? 0) === 1);
check('mysql source label', $mysqlReader->source() === 'mysql');

class FakeClickHouseQuery implements ClickHouseQuery
{
    public array $rows = [];

    public function rows(string $sql): array
    {
        return $this->rows;
    }
}

$chQuery = new FakeClickHouseQuery();
$chQuery->rows = [
    ['city_id' => $cityA, 'orders' => 10, 'delivered' => 8, 'cancelled' => 2, 'no_rider' => 1, 'gmv' => 4200.50],
];

$chReader = new ClickHouseCityAnalytics($chQuery);
$chRows = $chReader->daily([$cityA]);

check('clickhouse daily: mapped', ($chRows[$cityA]['orders'] ?? 0) === 10);
check('clickhouse daily: gmv decimal', ($chRows[$cityA]['gmv'] ?? 0) === 4200.5);
check('clickhouse source label', $chReader->source() === 'clickhouse');

class ThrowingQuery implements ClickHouseQuery
{
    public function rows(string $sql): array
    {
        throw new \RuntimeException('clickhouse down');
    }
}

$fallback = new FallbackCityAnalytics(new ClickHouseCityAnalytics(new ThrowingQuery()), $mysqlReader);
$fallbackRows = $fallback->daily([$cityA]);
check('fallback: uses mysql rows', ($fallbackRows[$cityA]['orders'] ?? 0) === 3);
check('fallback: source label', $fallback->source() === 'mysql_fallback');

$emptyQuery = new FakeClickHouseQuery();
$emptyFallback = new FallbackCityAnalytics(new ClickHouseCityAnalytics($emptyQuery), $mysqlReader);
$emptyRows = $emptyFallback->daily([$cityA]);
check('fallback: empty clickhouse uses mysql', ($emptyRows[$cityA]['orders'] ?? 0) === 3);
check('fallback: empty source label', $emptyFallback->source() === 'mysql_fallback_empty');

echo "=== Internal OTP hardening (per-phone limits) ===\n";

class FakeOtpSender implements \App\Services\Otp\OtpSender
{
    public bool $ok = true;
    public ?string $lastPhone = null;
    public ?string $lastCode = null;

    public function send(string $phone, string $code): bool
    {
        $this->lastPhone = $phone;
        $this->lastCode = $code;

        return $this->ok;
    }
}

$otpSender = new FakeOtpSender();
$otpSvc = new \App\Services\Otp\OtpService($otpSender);

$otpPhone = '9876500001';
$issued = $otpSvc->issue($otpPhone);
check('otp: sent', $issued['status'] === 'sent');
check('otp: 6-digit code generated', (bool) preg_match('/^[0-9]{6}$/', (string) $otpSender->lastCode));
check('otp: row persisted', Capsule::table('otps')->where('phone', $otpPhone)->count() === 1);

$verified = $otpSvc->verify($otpPhone, (string) $otpSender->lastCode);
check('otp: verify ok', $verified['status'] === 'ok' && $verified['otp']->verified === true);
check('otp: reuse rejected', $otpSvc->verify($otpPhone, (string) $otpSender->lastCode)['status'] === 'invalid');

$lockPhone = '9876500002';
$otpSvc->issue($lockPhone);
$wrongCode = $otpSender->lastCode === '000000' ? '111111' : '000000';
$attemptStatuses = [];
for ($i = 0; $i < 6; $i++) {
    $attemptStatuses[] = $otpSvc->verify($lockPhone, $wrongCode)['status'];
}
check('otp: wrong code increments attempts', $attemptStatuses[0] === 'invalid' && $attemptStatuses[3] === 'invalid');
check('otp: 5th wrong attempt locks', $attemptStatuses[4] === 'locked');
check('otp: locked stays locked', $attemptStatuses[5] === 'locked');

$throttlePhone = '9876500003';
$otpSvc->issue($throttlePhone);
$otpSvc->issue($throttlePhone);
$otpSvc->issue($throttlePhone);
check('otp: 4th request in 10m throttled', $otpSvc->issue($throttlePhone)['status'] === 'throttled');

$otpSender->ok = false;
$failedPhone = '9876500004';
check('otp: delivery failure surfaced', $otpSvc->issue($failedPhone)['status'] === 'delivery_failed');
check('otp: failed send not persisted', Capsule::table('otps')->where('phone', $failedPhone)->count() === 0);
$otpSender->ok = true;

check('otp: invalid phone rejected', $otpSvc->issue('12345')['status'] === 'invalid_phone');

// Pre-create the user via the query builder so the test stays framework-free
// (the User model's hashed-password cast needs a facade root).
Capsule::table('users')->insert([
    'id' => $uuid(),
    'name' => 'Linked Customer',
    'phone' => $otpPhone,
    'password' => 'x',
    'khaalo_coins' => 0,
    'created_at' => now(),
    'updated_at' => now(),
]);

// Sanctum's createToken needs config('sanctum.*'); give the standalone test a
// minimal container without installing a full facade root.
$smokeContainer = new \Illuminate\Container\Container();
\Illuminate\Container\Container::setInstance($smokeContainer);
$smokeContainer->instance('config', new \Illuminate\Config\Repository([
    'sanctum' => ['expiration' => null, 'token_prefix' => ''],
]));

$issuer = new \App\Services\Otp\CustomerTokenIssuer();
$tokenResult = $issuer->issue($otpPhone);
check('otp: token issued', is_string($tokenResult['token']) && $tokenResult['token'] !== '');
check('otp: token bound to phone user', ($tokenResult['user']->phone ?? null) === $otpPhone);
check('otp: sanctum token persisted', Capsule::table('personal_access_tokens')->count() === 1);

echo "=== Ranker dataset features ===\n";

$featureBuilder = new \App\Services\Ranker\RankerFeatureBuilder();

$features = $featureBuilder->features([
    'rating' => 4.5,
    'rating_count' => 100,
    'distance_km' => 2.1234567,
    'prep_time_minutes' => 20,
    'order_count_7d' => 30,
    'cancelled_30d' => 3,
    'orders_30d' => 100,
    'avg_price' => 250,
    'total_orders' => 500,
]);

check('ranker features: bayes rating', $features['bayes_rating'] === 4.416667);
check('ranker features: cancellation rate', $features['cancellation_rate'] === 0.03);
check('ranker features: distance rounded', $features['distance_km'] === 2.123457);
check('ranker features: established restaurant', $features['is_new'] === 0);

$newFeatures = $featureBuilder->features(['rating' => 4.0, 'rating_count' => 0, 'total_orders' => 5]);
check('ranker features: new restaurant flagged', $newFeatures['is_new'] === 1);
check('ranker features: no orders means zero cancel rate', $newFeatures['cancellation_rate'] === 0.0);

$header = $featureBuilder->csvHeader();
check('ranker features: csv header has label first', $header[0] === 'label');
check('ranker features: csv header has all features', count(array_intersect(\App\Services\Ranker\RankerFeatureBuilder::FEATURES, $header)) === 8);

echo "=== ONDC protocol core (signatures + on_search) ===\n";

$ondcKeypair = sodium_crypto_sign_keypair();
$ondcPublic = base64_encode(sodium_crypto_sign_publickey($ondcKeypair));
$ondcSecret = base64_encode(sodium_crypto_sign_secretkey($ondcKeypair));

$ondcSigner = new \App\Services\Ondc\OndcSigner('seller.test', 'key1', $ondcSecret);
$ondcVerifier = new \App\Services\Ondc\OndcVerifier([
    'seller.test|key1|ed25519' => $ondcPublic,
]);

$ondcBody = json_encode(['context' => ['transaction_id' => 'txn-1'], 'message' => ['intent' => ['item' => ['descriptor' => ['name' => 'biryani']]]]]);
$ondcHeader = $ondcSigner->sign($ondcBody, time(), 300);

$verifyOk = $ondcVerifier->verify($ondcHeader, $ondcBody);
check('ondc: signed header verifies', $verifyOk['ok'] === true);

$tampered = $ondcVerifier->verify($ondcHeader, $ondcBody.' ');
check('ondc: tampered body rejected', $tampered['ok'] === false && $tampered['error'] === 'invalid_signature');

$unknown = (new \App\Services\Ondc\OndcVerifier([]))->verify($ondcHeader, $ondcBody);
check('ondc: unknown key rejected', $unknown['ok'] === false && $unknown['error'] === 'unknown_key');

$expiredHeader = $ondcSigner->sign($ondcBody, time() - 3600, 60);
$expired = $ondcVerifier->verify($expiredHeader, $ondcBody);
check('ondc: expired window rejected', $expired['ok'] === false && $expired['error'] === 'expired_or_not_yet_valid');

$malformed = $ondcVerifier->verify('Bearer abc', $ondcBody);
check('ondc: malformed header rejected', $malformed['ok'] === false && $malformed['error'] === 'malformed_authorization');

$mapper = new \App\Services\Ondc\OndcCatalogMapper();
$provider = $mapper->provider([
    'id' => 'r-1',
    'name' => 'Biryani House',
    'latitude' => 22.7196,
    'longitude' => 75.8577,
], [[
    'id' => 'item-1',
    'name' => 'Hyderabadi Biryani',
    'price' => 199,
    'category_id' => 'cat-1',
]]);

check('ondc: provider id mapped', $provider['id'] === 'r-1');
check('ondc: gps mapped', $provider['locations'][0]['gps'] === '22.719600,75.857700');
check('ondc: item price normalized', $provider['items'][0]['price']['value'] === '199.00');
check('ondc: fulfillment mapped', $provider['fulfillments'][0]['type'] === 'Delivery');

$onSearch = $mapper->onSearch([
    'domain' => 'ONDC:RET10',
    'action' => 'search',
    'country' => 'IND',
    'city' => 'std:081',
    'bap_id' => 'buyer.test',
    'bap_uri' => 'https://buyer.test',
    'transaction_id' => 'txn-1',
    'message_id' => 'msg-1',
    'timestamp' => '2026-10-21T18:00:00Z',
], [$provider], '2026-10-21T18:00:01Z', [
    'bpp_id' => 'seller.test',
    'bpp_uri' => 'https://seller.test',
]);

check('ondc: action becomes on_search', $onSearch['context']['action'] === 'on_search');
check('ondc: bpp id attached', $onSearch['context']['bpp_id'] === 'seller.test');
check('ondc: provider list present', count($onSearch['message']['catalog']['bpp/providers']) === 1);
check('ondc: fulfillment policy present', $onSearch['message']['catalog']['bpp/fulfillments'][0]['type'] === 'Delivery');
check('ondc: timestamp supplied', $onSearch['context']['timestamp'] === '2026-10-21T18:00:01Z');

$callbackCapture = [];
$callbackClient = new \App\Services\Ondc\OndcCallbackClient(
    $ondcSigner,
    function (string $method, string $url, array $headers, string $body) use (&$callbackCapture): array {
        $callbackCapture = compact('method', 'url', 'headers', 'body');
        return ['ok' => true, 'status' => 200];
    }
);

$callbackResult = $callbackClient->send($onSearch, 'https://buyer.test/ondc/on_search');
check('ondc callback: sent ok', $callbackResult['ok'] === true && $callbackResult['status'] === 200);
check('ondc callback: POST to bap uri', $callbackCapture['method'] === 'POST'
    && $callbackCapture['url'] === 'https://buyer.test/ondc/on_search');
check('ondc callback: content type json', ($callbackCapture['headers']['Content-Type'] ?? null) === 'application/json');

$callbackVerify = $ondcVerifier->verify($callbackCapture['headers']['Authorization'] ?? '', $callbackCapture['body'] ?? '');
check('ondc callback: signature verifies', $callbackVerify['ok'] === true);

$failingCallback = new \App\Services\Ondc\OndcCallbackClient(
    $ondcSigner,
    fn () => ['ok' => false, 'status' => 500]
);
$failedCallbackResult = $failingCallback->send($onSearch, 'https://buyer.test/ondc/on_search');
check('ondc callback: failure surfaced', $failedCallbackResult['ok'] === false && $failedCallbackResult['status'] === 500);

echo "=== SOS safety flow (incident + partner hook) ===\n";

class FakeSosNotifier implements \App\Services\Sos\SosPartnerNotifier
{
    public int $calls = 0;

    public function notify(\App\Models\SosAlert $alert): void
    {
        $this->calls++;
    }
}

$riderOps = User::create([
    'name' => 'Rider Ops',
    'email' => 'riderops-'.uniqid().'@test.local',
    'is_admin' => true,
    'admin_role' => 'rider_ops',
]);

$sosRider = \App\Models\Rider::create(['name' => 'Ravi Kumar', 'phone' => '9876543210']);

$notifier = new FakeSosNotifier();
$publisherForSos = new \App\Services\OutboxPublisher();
$sosSvc = new \App\Services\Sos\SosService(
    $publisherForSos,
    new \App\Services\IncidentService($publisherForSos),
    $notifier,
);

$triggered = $sosSvc->trigger($sosRider, [
    'latitude' => 22.7196,
    'longitude' => 75.8577,
    'note' => 'Following me',
    'battery' => 12,
]);

$alert = $triggered['alert'];
check('sos: created open', $alert->status === 'open' && $triggered['deduplicated'] === false);
check('sos: auto incident linked', $alert->incident_id !== null);

$incidentRow = \App\Models\OpsIncident::find($alert->incident_id);
check('sos: incident is sev1 safety', $incidentRow
    && $incidentRow->severity === 'sev1' && $incidentRow->category === 'safety');

check('sos: partner notified once', $notifier->calls === 1);
check('sos: event emitted', Capsule::table('outbox')
    ->where('aggregate_id', $alert->id)
    ->where('event_type', 'safety.sos_triggered')
    ->exists());

$duplicate = $sosSvc->trigger($sosRider, ['note' => 'Still following me']);
check('sos: duplicate deduped', $duplicate['deduplicated'] === true && $duplicate['alert']->id === $alert->id);
check('sos: duplicate does not re-notify', $notifier->calls === 1);
check('sos: duplicate does not create a second incident', \App\Models\OpsIncident::where('category', 'safety')->count() === 1);

$acked = $sosSvc->acknowledge($alert->fresh(), $riderOps);
check('sos: acknowledged', $acked->status === 'ack' && $acked->acknowledged_by === $riderOps->id);

$doubleAck = false;
try {
    $sosSvc->acknowledge($acked, $riderOps);
} catch (\DomainException $e) {
    $doubleAck = true;
}
check('sos: double ack rejected', $doubleAck);

$shortNote = false;
try {
    $sosSvc->resolve($acked, $riderOps, 'ok');
} catch (\DomainException $e) {
    $shortNote = true;
}
check('sos: short resolution rejected', $shortNote);

$resolved = $sosSvc->resolve($acked, $riderOps, 'Police contacted; rider safe and escorted.');
check('sos: resolved', $resolved->status === 'resolved' && $resolved->resolved_at !== null);

$perms = \App\Http\Middleware\AdminAuth::ROLE_PERMISSIONS;
check('roles: rider_ops manages SOS', in_array('sos.manage', $perms['rider_ops'], true));
check('roles: auditor views SOS only', in_array('sos.view', $perms['auditor'], true)
    && ! in_array('sos.manage', $perms['auditor'], true));
check('roles: support_l1 has no SOS access', ! in_array('sos.view', $perms['support_l1'], true));

echo "=== PII retention (dry-run + audited apply) ===\n";

$retention = new \App\Services\PiiRetentionService();

\App\Models\PiiAccessLog::create([
    'actor_user_id' => $buyer->id,
    'action' => 'export',
    'resource_type' => 'legacy_orders',
    'filters' => ['year' => 2024],
    'row_count' => 10,
    'created_at' => now()->subDays(200),
]);
\App\Models\PiiAccessLog::create([
    'actor_user_id' => $buyer->id,
    'action' => 'view',
    'resource_type' => 'legacy_orders',
    'filters' => [],
    'row_count' => 1,
    'created_at' => now()->subDays(190),
]);
\App\Models\PiiAccessLog::create([
    'actor_user_id' => $buyer->id,
    'action' => 'view',
    'resource_type' => 'fresh_orders',
    'filters' => [],
    'row_count' => 1,
    'created_at' => now()->subDays(5),
]);

try {
    $retention->cutoff(10);
    check('retention: min window enforced', false);
} catch (\DomainException $e) {
    check('retention: min window enforced', true);
}

$cutoff = $retention->cutoff(90);
$preview = $retention->preview($cutoff);
check('retention: preview counts old rows', $preview['total'] === 2);
check('retention: preview by action', ($preview['by_action']['export'] ?? 0) === 1 && ($preview['by_action']['view'] ?? 0) === 1);
check('retention: preview by resource', ($preview['by_resource']['legacy_orders'] ?? 0) === 2);

$dry = $retention->purge($cutoff, $buyer, true);
check('retention: dry-run deletes nothing', $dry['rows_deleted'] === 0 && $dry['dry_run'] === true);
check('retention: dry-run recorded', \App\Models\PiiRetentionRun::where('id', $dry['run_id'])->where('dry_run', true)->exists());
check('retention: old rows still present after dry-run', \App\Models\PiiAccessLog::where('resource_type', 'legacy_orders')->count() === 2);

$apply = $retention->purge($cutoff, $buyer, false);
check('retention: apply deletes old rows', $apply['rows_deleted'] === 2);
check('retention: modern rows survive', \App\Models\PiiAccessLog::where('resource_type', 'fresh_orders')->count() === 1);
check('retention: apply recorded', \App\Models\PiiRetentionRun::where('id', $apply['run_id'])
    ->where('dry_run', false)->where('rows_deleted', 2)->exists());
check('retention: second apply deletes nothing', $retention->purge($cutoff, $buyer, false)['rows_deleted'] === 0);

echo "=== Shift auto-rotation ===\n";

$rotPlanner = new \App\Services\ShiftRotationPlanner();
$rotWeek = now()->startOfWeek(\Carbon\Carbon::MONDAY)->addWeek()->startOfDay();
$rotCity = (string) $uuid();

$rotUserA = User::create(['name' => 'Rot A', 'email' => 'rota-'.uniqid().'@test.local', 'is_admin' => true, 'admin_role' => 'support_l1']);
$rotUserB = User::create(['name' => 'Rot B', 'email' => 'rotb-'.uniqid().'@test.local', 'is_admin' => true, 'admin_role' => 'support_l1']);
$rotUserC = User::create(['name' => 'Rot C', 'email' => 'rotc-'.uniqid().'@test.local', 'is_admin' => true, 'admin_role' => 'support_l1']);

foreach ([[$rotUserA, $rotCity], [$rotUserB, $rotCity], [$rotUserC, (string) $uuid()]] as [$u, $city]) {
    \App\Models\WaWorkforce::create([
        'user_id' => $u->id,
        'city_id' => $city,
        'shift_start' => '09:00',
        'shift_end' => '18:00',
        'queue_cap' => 10,
        'active' => true,
    ]);
}

$plan = $rotPlanner->plan($rotWeek, [
    'city_id' => $rotCity, 'days' => 7, 'per_day' => 1, 'hours' => 8, 'start_time' => '09:00',
]);

check('rotation: one entry per day', count($plan['entries']) === 7);
check('rotation: no gaps with two agents', $plan['gaps'] === []);
check('rotation: agents alternate', $plan['entries'][0]['user_id'] !== $plan['entries'][1]['user_id']);
check('rotation: each shift is 8h', (int) round(\Carbon\Carbon::parse($plan['entries'][0]['starts_at'])
    ->diffInHours(\Carbon\Carbon::parse($plan['entries'][0]['ends_at']))) === 8);

$dayOne = \Carbon\Carbon::parse($plan['entries'][0]['starts_at']);
\App\Models\OpsShift::create([
    'user_id' => $plan['entries'][0]['user_id'],
    'city_id' => $rotCity,
    'starts_at' => $dayOne->copy()->setTimeFromTimeString('09:00'),
    'ends_at' => $dayOne->copy()->setTimeFromTimeString('17:00'),
    'status' => 'scheduled',
]);

$replan = $rotPlanner->plan($rotWeek, [
    'city_id' => $rotCity, 'days' => 7, 'per_day' => 1, 'hours' => 8, 'start_time' => '09:00',
]);
$dayOneEntry = collect($replan['entries'])->first(
    fn ($e) => \Carbon\Carbon::parse($e['starts_at'])->toDateString() === $dayOne->toDateString()
);
check('rotation: existing shift respected', $dayOneEntry && $dayOneEntry['user_id'] !== $plan['entries'][0]['user_id']);
check('rotation: still one entry per day', count($replan['entries']) === 7);

$gapCity = (string) $uuid();
\App\Models\WaWorkforce::create([
    'user_id' => $rotUserC->id,
    'city_id' => $gapCity,
    'shift_start' => '09:00', 'shift_end' => '18:00', 'queue_cap' => 10, 'active' => true,
]);
$gapPlan = $rotPlanner->plan($rotWeek, [
    'city_id' => $gapCity, 'days' => 7, 'per_day' => 2, 'hours' => 8, 'start_time' => '09:00',
]);
check('rotation: gaps reported for insufficient staff', count($gapPlan['gaps']) === 7);
check('rotation: still schedules available agent', count($gapPlan['entries']) === 7);

$supportLead = User::create([
    'name' => 'Support Lead',
    'email' => 'lead-'.uniqid().'@test.local',
    'is_admin' => true,
    'admin_role' => 'support_lead',
]);

$gapSvc = new \App\Services\Shifts\RotationGapIncidentService(
    new \App\Services\IncidentService(new \App\Services\OutboxPublisher())
);

$raised = $gapSvc->raise($gapPlan, $buyer);
check('rotation gaps: incidents created per gap day', $raised['created'] === count($gapPlan['gaps']));
check('rotation gaps: staffing incidents persisted', \App\Models\OpsIncident::where('category', 'staffing')->count() === count($gapPlan['gaps']));
check('rotation gaps: support_lead owns incidents', \App\Models\OpsIncident::where('category', 'staffing')
    ->where('owner_user_id', $supportLead->id)->count() === count($gapPlan['gaps']));
check('rotation gaps: sev3 severity', \App\Models\OpsIncident::where('category', 'staffing')
    ->where('severity', 'sev3')->count() === count($gapPlan['gaps']));

$raisedAgain = $gapSvc->raise($gapPlan, $buyer);
check('rotation gaps: re-raise dedupes', $raisedAgain['created'] === 0 && $raisedAgain['skipped'] === count($gapPlan['gaps']));
check('rotation gaps: no duplicate incidents', \App\Models\OpsIncident::where('category', 'staffing')->count() === count($gapPlan['gaps']));

$noGapRaised = $gapSvc->raise(['week_start' => $rotWeek->toDateString(), 'gaps' => []], $buyer);
check('rotation gaps: full coverage raises nothing', $noGapRaised['created'] === 0);

$applyCity = (string) $uuid();
$applyA = User::create(['name' => 'Apply A', 'email' => 'applya-'.uniqid().'@test.local', 'is_admin' => true, 'admin_role' => 'support_l1']);
$applyB = User::create(['name' => 'Apply B', 'email' => 'applyb-'.uniqid().'@test.local', 'is_admin' => true, 'admin_role' => 'support_l1']);
foreach ([$applyA, $applyB] as $u) {
    \App\Models\WaWorkforce::create([
        'user_id' => $u->id, 'city_id' => $applyCity,
        'shift_start' => '09:00', 'shift_end' => '18:00', 'queue_cap' => 10, 'active' => true,
    ]);
}

$applyPlan = $rotPlanner->plan($rotWeek, [
    'city_id' => $applyCity, 'days' => 7, 'per_day' => 1, 'hours' => 8, 'start_time' => '09:00',
]);
$applied = $rotPlanner->apply($applyPlan, $buyer);
check('rotation: apply creates shifts', $applied['created'] === 7 && $applied['skipped'] === 0);
check('rotation: creator recorded', \App\Models\OpsShift::where('city_id', $applyCity)->where('created_by', $buyer->id)->count() === 7);

$reapplied = $rotPlanner->apply($applyPlan, $buyer);
check('rotation: re-apply is overlap-safe', $reapplied['created'] === 0 && $reapplied['skipped'] === 7);

$clampPlan = $rotPlanner->plan($rotWeek, [
    'city_id' => $rotCity, 'days' => 1, 'per_day' => 1, 'hours' => 20, 'start_time' => '09:00',
]);
$clampedHours = (int) round(\Carbon\Carbon::parse($clampPlan['entries'][0]['starts_at'])
    ->diffInHours(\Carbon\Carbon::parse($clampPlan['entries'][0]['ends_at'])));
check('rotation: hours clamp to 12', $clampedHours === 12);

echo "=== Ops shifts (org-unit coverage) ===\n";

$shiftSvc = new \App\Services\ShiftService();
$shiftCity = $uuid();

$future = $shiftSvc->create([
    'user_id' => $buyer->id,
    'city_id' => $shiftCity,
    'role' => 'support_l1',
    'starts_at' => now()->addHour()->toDateTimeString(),
    'ends_at' => now()->addHours(3)->toDateTimeString(),
    'notes' => 'Evening queue',
], $buyer);

check('shift: scheduled', $future->status === 'scheduled');
check('shift: created_by recorded', $future->created_by === $buyer->id);
check('shift: city recorded', $future->city_id === $shiftCity);

$overlapRejected = false;
try {
    $shiftSvc->create([
        'user_id' => $buyer->id,
        'starts_at' => now()->addHours(2)->toDateTimeString(),
        'ends_at' => now()->addHours(4)->toDateTimeString(),
    ], $buyer);
} catch (\DomainException $e) {
    $overlapRejected = true;
}
check('shift: overlap rejected', $overlapRejected);

$badWindow = false;
try {
    $shiftSvc->create([
        'user_id' => $buyer->id,
        'starts_at' => now()->addHours(2)->toDateTimeString(),
        'ends_at' => now()->addHour()->toDateTimeString(),
    ], $buyer);
} catch (\DomainException $e) {
    $badWindow = true;
}
check('shift: end before start rejected', $badWindow);

$tooLong = false;
try {
    $shiftSvc->create([
        'user_id' => $buyer->id,
        'starts_at' => now()->addHours(1)->toDateTimeString(),
        'ends_at' => now()->addHours(14)->toDateTimeString(),
    ], $buyer);
} catch (\DomainException $e) {
    $tooLong = true;
}
check('shift: >12h rejected', $tooLong);

$nowShift = $shiftSvc->create([
    'user_id' => $buyer->id,
    'city_id' => $shiftCity,
    'role' => 'support_l1',
    'starts_at' => now()->subHour()->toDateTimeString(),
    'ends_at' => now()->addHour()->toDateTimeString(),
], $buyer);

$onShift = $shiftSvc->onShift($shiftCity);
check('shift: on-shift finds current agent', count($onShift) === 1 && $onShift[0]->user_id === $buyer->id);

$coverage = $shiftSvc->coverage($shiftCity, now()->toDateString());
check('shift: coverage counts agent once', $coverage['agents'] === 1);
check('shift: coverage counts both shifts', $coverage['shifts'] === 2);

$active = $shiftSvc->activate($future->fresh());
check('shift: activate works', $active->status === 'active');

$doubleActivate = false;
try {
    $shiftSvc->activate($active);
} catch (\DomainException $e) {
    $doubleActivate = true;
}
check('shift: double activate rejected', $doubleActivate);

$cancelled = $shiftSvc->cancel($nowShift->fresh(), 'Agent moved to incident response.');
check('shift: cancel works', $cancelled->status === 'cancelled');
check('shift: cancel reason stored', str_contains((string) $cancelled->cancelled_reason, 'incident'));
check('shift: cancelled leaves on-shift set', count($shiftSvc->onShift($shiftCity)) === 0);

$perms = \App\Http\Middleware\AdminAuth::ROLE_PERMISSIONS;
check('roles: city_ops manages shifts', in_array('shifts.manage', $perms['city_ops'], true));
check('roles: support_lead manages shifts', in_array('shifts.manage', $perms['support_lead'], true));
check('roles: whatsapp_ops views shifts only', in_array('shifts.view', $perms['whatsapp_ops'], true)
    && ! in_array('shifts.manage', $perms['whatsapp_ops'], true));
check('roles: auditor views shifts only', in_array('shifts.view', $perms['auditor'], true)
    && ! in_array('shifts.manage', $perms['auditor'], true));

echo "=== PII access audit (append-only) ===\n";

$pii = new \App\Services\PiiAccessLogger();
$piiRowsBefore = Capsule::table('pii_access_logs')->count();

$first = $pii->log(
    $buyer,
    'export',
    'orders',
    null,
    ['status' => 'delivered', 'restaurant_id' => 'r-1'],
    42,
    'admin_orders_export',
    ['ip' => '10.0.0.9', 'user_agent' => 'phpunit'],
);

check('pii: action stored', $first->action === 'export');
check('pii: resource stored', $first->resource_type === 'orders');
check('pii: row count stored', $first->row_count === 42);
check('pii: filters stored as array', is_array($first->filters) && $first->filters['status'] === 'delivered');
check('pii: ip stored', $first->ip_address === '10.0.0.9');
check('pii: actor role nullable', $first->actor_role === null);

$pii->log($buyer, 'view', 'customers', (string) $uuid(), [], 1, 'support_lookup', ['ip' => '10.0.0.9']);
check('pii: append-only (two rows)', Capsule::table('pii_access_logs')->count() === $piiRowsBefore + 2);

$invalid = false;
try {
    $pii->log($buyer, 'teleport', 'orders');
} catch (\DomainException $e) {
    $invalid = true;
}
check('pii: unsupported action rejected', $invalid);

$perms = \App\Http\Middleware\AdminAuth::ROLE_PERMISSIONS;
check('roles: auditor can view PII audit', in_array('audit.view', $perms['auditor'], true));
check('roles: support_l1 cannot view PII audit', ! in_array('audit.view', $perms['support_l1'], true));
check('roles: super_admin wildcard', in_array('*', $perms['super_admin'], true));

echo "=== Ops incidents (SLA + ownership) ===\n";

$incidentSvc = new \App\Services\IncidentService(new \App\Services\OutboxPublisher());

$incident = $incidentSvc->create([
    'severity' => 'sev2',
    'category' => 'riders',
    'title' => 'Rider supply drop in Indore',
    'description' => 'Online riders below threshold for 20 minutes.',
], $buyer);

check('incident: created open', $incident->status === 'open');
check('incident: severity stored', $incident->severity === 'sev2');
check('incident: SLA 15 minutes', (int) abs($incident->sla_due_at->diffInMinutes($incident->opened_at)) === 15);
check('incident: opened event in outbox', Capsule::table('outbox')
    ->where('aggregate_id', $incident->id)
    ->where('event_type', 'ops_incident.opened')
    ->exists());

$acked = $incidentSvc->acknowledge($incident->fresh(), $buyer);
check('incident: ack sets status', $acked->status === 'ack');
check('incident: ack timestamp', $acked->ack_at !== null);
check('incident: ack owns incident', $acked->owner_user_id === $buyer->id);

$doubleAck = false;
try {
    $incidentSvc->acknowledge($acked, $buyer);
} catch (\DomainException $e) {
    $doubleAck = true;
}
check('incident: double ack rejected', $doubleAck);

$shortNote = false;
try {
    $incidentSvc->resolve($acked, $buyer, 'fixed');
} catch (\DomainException $e) {
    $shortNote = true;
}
check('incident: short resolution note rejected', $shortNote);

$resolved = $incidentSvc->resolve($acked, $buyer, 'Supply restored; two riders moved from zone 4.');
check('incident: resolved', $resolved->status === 'resolved' && $resolved->resolved_at !== null);
check('incident: resolution note stored', str_contains((string) $resolved->resolution_note, 'Supply restored'));

$resolveAgain = false;
try {
    $incidentSvc->resolve($resolved, $buyer, 'Trying to resolve a closed incident.');
} catch (\DomainException $e) {
    $resolveAgain = true;
}
check('incident: resolve twice rejected', $resolveAgain);

$breach = $incidentSvc->create([
    'severity' => 'sev1',
    'category' => 'payments',
    'title' => 'Razorpay capture failures',
], $buyer);
$breach->update(['sla_due_at' => now()->subMinutes(7)]);

check('incident: breach sweep flags one', $incidentSvc->markBreaches() === 1);
check('incident: breached timestamp set', $breach->fresh()->sla_breached_at !== null);
check('incident: breach sweep is idempotent', $incidentSvc->markBreaches() === 0);
check('incident: breach event in outbox', Capsule::table('outbox')
    ->where('aggregate_id', $breach->id)
    ->where('event_type', 'ops_incident.sla_breached')
    ->exists());

$perms = \App\Http\Middleware\AdminAuth::ROLE_PERMISSIONS;
check('roles: city_ops manages incidents', in_array('incidents.manage', $perms['city_ops'], true));
check('roles: support_lead manages incidents', in_array('incidents.manage', $perms['support_lead'], true));

$incidentLead = User::create([
    'name' => 'Incident Lead',
    'email' => 'incident-lead-'.uniqid().'@test.local',
    'is_admin' => true,
    'admin_role' => 'support_lead',
]);

$riskIncident = $incidentSvc->create([
    'severity' => 'sev2',
    'category' => 'infra',
    'title' => 'Payment gateway latency rising',
], $buyer);
$riskIncident->update([
    'opened_at' => now()->subMinutes(10),
    'sla_due_at' => now()->addMinutes(5),
]);

check('incident escalation: at-risk flagged once', $incidentSvc->escalateAtRisk() === 1);
check('incident escalation: level 1 set', $riskIncident->fresh()->escalation_level === 1);
check('incident escalation: support_lead owns at-risk', ($riskIncident->fresh()->owner?->admin_role ?? null) === 'support_lead');
check('incident escalation: at-risk event emitted', Capsule::table('outbox')
    ->where('aggregate_id', $riskIncident->id)
    ->where('event_type', 'ops_incident.sla_at_risk')
    ->exists());
check('incident escalation: at-risk is idempotent', $incidentSvc->escalateAtRisk() === 0);
check('incident escalation: breach bumps to level 2', $breach->fresh()->escalation_level === 2);
check('incident escalation: breached unowned gets lead', ($breach->fresh()->owner?->admin_role ?? null) === 'support_lead');

$onCallCity = (string) $uuid();
$onCallLead = User::create([
    'name' => 'On-call Lead',
    'email' => 'oncall-'.uniqid().'@test.local',
    'is_admin' => true,
    'admin_role' => 'support_lead',
]);
\App\Models\OpsShift::create([
    'user_id' => $onCallLead->id,
    'city_id' => $onCallCity,
    'role' => 'support_lead',
    'starts_at' => now()->subHour(),
    'ends_at' => now()->addHour(),
    'status' => 'active',
]);

$cityIncident = $incidentSvc->create([
    'severity' => 'sev2',
    'category' => 'infra',
    'title' => 'City network degradation',
    'city_id' => $onCallCity,
], $buyer);
$cityIncident->update(['sla_due_at' => now()->subMinutes(1)]);
$incidentSvc->markBreaches();
check('incident escalation: on-shift lead owns city incident', $cityIncident->fresh()->owner_user_id === $onCallLead->id);

$assignedLead = User::create([
    'name' => 'Assigned Lead', 'email' => 'assigned-'.uniqid().'@test.local',
    'is_admin' => true, 'admin_role' => 'support_lead',
]);
$backupLead = User::create([
    'name' => 'Backup Lead', 'email' => 'backup-'.uniqid().'@test.local',
    'is_admin' => true, 'admin_role' => 'support_lead',
]);
\App\Models\OrgRole::create([
    'org_unit_id' => (string) $uuid(),
    'title' => 'Support Lead (with backup)',
    'assigned_user_id' => $assignedLead->id,
    'backup_user_id' => $backupLead->id,
    'is_active' => true,
]);

$backupCity = (string) $uuid();
$backupIncident = $incidentSvc->create([
    'severity' => 'sev2', 'category' => 'infra', 'title' => 'Backup on-call check', 'city_id' => $backupCity,
], $buyer);
$backupIncident->update(['sla_due_at' => now()->subMinutes(1)]);
$incidentSvc->markBreaches();
check('incident escalation: OrgRole backup owns city incident', $backupIncident->fresh()->owner_user_id === $backupLead->id);
check('roles: whatsapp_ops views incidents only', in_array('incidents.view', $perms['whatsapp_ops'], true)
    && ! in_array('incidents.manage', $perms['whatsapp_ops'], true));
check('roles: auditor views but cannot manage', in_array('incidents.view', $perms['auditor'], true)
    && ! in_array('incidents.manage', $perms['auditor'], true));

echo "=== Pricing parity comparator + cutover scope ===\n";

use App\Services\Pricing\PricingParityComparator;

$cmp = new PricingParityComparator();
$canonical = [
    'grandTotal' => '230.50',
    'tax' => '9.45',
    'vendorDiscount' => '9.95',
    'deliveryFee' => '27.00',
    'platformFee' => '5.00',
    'couponDiscount' => '0.00',
    'commission' => '28.36',
    'netPayout' => '165.04',
];

check('comparator: identical payloads match', $cmp->compare($canonical, $canonical) === []);
check('comparator: numeric formatting tolerated', $cmp->compare(
    ['grandTotal' => '230.5'] + $canonical,
    ['grandTotal' => '230.50'] + $canonical,
) === []);

$drift = $canonical;
$drift['tax'] = '9.46';
$diff = $cmp->compare($canonical, $drift);
check('comparator: one paise difference detected', isset($diff['tax'])
    && $diff['tax']['laravel'] === '9.45' && $diff['tax']['java'] === '9.46');

$missing = $canonical;
unset($missing['commission']);
$missingDiff = $cmp->compare($canonical, $missing);
check('comparator: missing java field detected', isset($missingDiff['commission'])
    && $missingDiff['commission']['java'] === null);

$scopeRouter = new \App\Services\OrderCommandRouter([
    'cutover_enabled' => true,
    'cutover_cities' => ['city-1'],
    'cutover_restaurants' => ['rest-9'],
]);
check('scope: allowlisted city', $scopeRouter->inCutoverScope('city-1', null) === true);
check('scope: allowlisted restaurant', $scopeRouter->inCutoverScope(null, 'rest-9') === true);
check('scope: unlisted is out', $scopeRouter->inCutoverScope('city-2', 'rest-2') === false);
check('scope: disabled is out', (new \App\Services\OrderCommandRouter(['cutover_enabled' => false]))->inCutoverScope('city-1', null) === false);

echo "=== Order projection (Java → Laravel read model) ===\n";

$projectionSvc = new \App\Services\Projection\OrderProjectionService($capsule->getDatabaseManager());
$projOrderId = (string) $uuid();

$createdEnvelope = [
    'event_id' => (string) $uuid(),
    'event_type' => 'order.created',
    'occurred_at' => now()->toIso8601String(),
    'data' => [
        'orderId' => $projOrderId,
        'userId' => (string) $buyer->id,
        'restaurantId' => (string) $uuid(),
        'cityId' => (string) $uuid(),
        'channel' => 'WHATSAPP',
        'status' => 'PENDING',
        'cancelReason' => null,
        'version' => 0,
        'grandTotal' => 230.5,
        'pricing' => [
            'grandTotal' => '230.50',
            'tax' => '9.45',
            'vendorDiscount' => '9.95',
            'deliveryFee' => '27.00',
            'platformFee' => '5.00',
            'couponDiscount' => '0.00',
            'commission' => '28.36',
            'commissionGst' => '5.10',
            'netPayout' => '165.04',
        ],
    ],
];

$createdResult = $projectionSvc->apply($createdEnvelope);
check('projection: created applied', $createdResult['applied'] === true);

$projected = Capsule::table('projected_orders')->where('order_id', $projOrderId)->first();
check('projection: status pending', $projected->status === 'pending');
check('projection: channel lowercased', $projected->channel === 'whatsapp');
check('projection: pricing persisted', (float) $projected->grand_total === 230.5);
check('projection: version stored', (int) $projected->version === 0);

$duplicate = $projectionSvc->apply($createdEnvelope);
check('projection: duplicate event ignored', $duplicate['applied'] === false && $duplicate['reason'] === 'duplicate');

$statusEnvelope = $createdEnvelope;
$statusEnvelope['event_id'] = (string) $uuid();
$statusEnvelope['event_type'] = 'order.status_changed';
$statusEnvelope['data']['status'] = 'CONFIRMED';
$statusEnvelope['data']['version'] = 1;
check('projection: status change applied', $projectionSvc->apply($statusEnvelope)['applied'] === true);
check('projection: status updated', Capsule::table('projected_orders')->where('order_id', $projOrderId)->value('status') === 'confirmed');

$staleEnvelope = $createdEnvelope;
$staleEnvelope['event_id'] = (string) $uuid();
$staleEnvelope['event_type'] = 'order.status_changed';
$staleEnvelope['data']['status'] = 'PENDING';
$staleEnvelope['data']['version'] = 0;
$stale = $projectionSvc->apply($staleEnvelope);
check('projection: stale version rejected', $stale['applied'] === false && $stale['reason'] === 'stale_version');
check('projection: stale does not regress status', Capsule::table('projected_orders')->where('order_id', $projOrderId)->value('status') === 'confirmed');

$unsupported = $createdEnvelope;
$unsupported['event_id'] = (string) $uuid();
$unsupported['event_type'] = 'order.teleported';
check('projection: unsupported event ignored', $projectionSvc->apply($unsupported)['reason'] === 'unsupported_event');

$projectionStatus = $projectionSvc->status();
check('projection: status total', $projectionStatus['total'] === 1);
check('projection: status by state', ($projectionStatus['by_status']['confirmed'] ?? 0) === 1);

echo "=== Projection transport runner ===\n";

class FakeOrderEventSource implements \App\Services\Projection\OrderEventSource
{
    public array $records = [];
    public array $acked = [];
    public bool $closed = false;

    public function poll(int $limit, int $timeoutMs): array
    {
        $batch = array_slice($this->records, 0, $limit);
        $this->records = array_slice($this->records, count($batch));

        return $batch;
    }

    public function ack(array $record): void
    {
        $this->acked[] = $record['id'];
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

$runner = new \App\Services\Projection\ProjectionRunner();
$runnerOrderId = (string) $uuid();

$runnerCreated = $createdEnvelope;
$runnerCreated['event_id'] = (string) $uuid();
$runnerCreated['data']['orderId'] = $runnerOrderId;

$runnerStatus = $runnerCreated;
$runnerStatus['event_id'] = (string) $uuid();
$runnerStatus['event_type'] = 'order.status_changed';
$runnerStatus['data']['status'] = 'PREPARING';
$runnerStatus['data']['version'] = 1;

$source = new FakeOrderEventSource();
$source->records = [
    ['id' => 'r-1', 'envelope' => $runnerCreated, 'meta' => []],
    ['id' => 'r-2', 'envelope' => $runnerStatus, 'meta' => []],
];

$runResult = $runner->run($source, $projectionSvc, 10, 10);
check('runner: reads batch', $runResult['read'] === 2);
check('runner: applies both', $runResult['applied'] === 2 && $runResult['failed'] === 0);
check('runner: acks after projection', $source->acked === ['r-1', 'r-2']);
check('runner: projected final status', Capsule::table('projected_orders')->where('order_id', $runnerOrderId)->value('status') === 'preparing');

class ThrowingProjectionService extends \App\Services\Projection\OrderProjectionService
{
    public function __construct()
    {
    }

    public function apply(array $envelope): array
    {
        throw new \RuntimeException('boom');
    }
}

$failSource = new FakeOrderEventSource();
$failSource->records = [['id' => 'r-fail', 'envelope' => $runnerCreated, 'meta' => []]];
$failResult = $runner->run($failSource, new ThrowingProjectionService(), 10, 10);
check('runner: failure reported', $failResult['failed'] === 1);
check('runner: failure does not ack', $failSource->acked === []);

$badTransport = false;
try {
    (new \App\Services\Projection\OrderEventSourceFactory())->make('carrier-pigeon');
} catch (\RuntimeException $e) {
    $badTransport = true;
}
check('runner: unsupported transport rejected', $badTransport);

echo "=== GPS anomaly + city capacity guards ===\n";

$gpsGuard = new \App\Services\Gps\GpsSpeedGuard();
$nowTs = now();

check('gps: first ping always allowed', $gpsGuard->isPlausible(null, null, null, 22.7, 75.8, $nowTs));
check('gps: normal movement allowed', $gpsGuard->isPlausible(22.70, 75.80, $nowTs->copy()->subMinute(), 22.709, 75.80, $nowTs));
check('gps: impossible jump rejected', ! $gpsGuard->isPlausible(22.70, 75.80, $nowTs->copy()->subMinute(), 22.80, 75.80, $nowTs));
check('gps: sustained 132 km/h rejected', ! $gpsGuard->isPlausible(22.70, 75.80, $nowTs->copy()->subMinutes(5), 22.799, 75.80, $nowTs));

$capacityCity = (string) $uuid();
$capacityGuard = new \App\Services\City\CityCapacityGuard();

for ($i = 0; $i < 2; $i++) {
    Order::create([
        'user_id' => $buyer->id,
        'city_id' => $capacityCity,
        'status' => 'pending',
        'payment_mode' => 'cod',
        'payment_status' => 'pending',
        'grand_total' => 100,
        'subtotal' => 100,
    ]);
}

check('capacity: under cap allowed', $capacityGuard->allow($capacityCity, 3));
check('capacity: at cap rejected', ! $capacityGuard->allow($capacityCity, 2));
check('capacity: zero cap disabled', $capacityGuard->allow($capacityCity, 0));
check('capacity: no city allowed', $capacityGuard->allow(null, 1));

echo "=== OpenSearch client + documents ===\n";

$searchCapture = [];
$searchClient = new \App\Services\Search\OpenSearchClient(
    'http://search.test:9200',
    'restaurants_test',
    function (string $method, string $url, array $headers, string $body) use (&$searchCapture): array {
        $searchCapture[] = compact('method', 'url', 'headers', 'body');

        if (str_contains($url, '_bulk')) {
            return ['ok' => true, 'status' => 200, 'body' => '{}'];
        }

        return ['ok' => true, 'status' => 200, 'body' => json_encode([
            'hits' => ['total' => ['value' => 1], 'hits' => [['_source' => ['id' => 'r-1', 'name' => 'Biryani House']]]],
        ])];
    }
);

$searchResult = $searchClient->search(['query' => ['match_all' => (object) []]]);
check('search: query returns hits', isset($searchResult['hits']['hits'][0]));
check('search: url targets index', str_contains($searchCapture[0]['url'], '/restaurants_test/_search'));
check('search: query body sent', str_contains($searchCapture[0]['body'], 'match_all'));

$bulkOk = $searchClient->bulk([
    ['id' => 'r-1', 'name' => 'A'],
    ['id' => 'r-2', 'name' => 'B'],
]);
check('search: bulk indexed', $bulkOk === true);
$bulkBody = $searchCapture[1]['body'] ?? '';
check('search: bulk uses ndjson pairs', substr_count(trim($bulkBody), "\n") >= 3 && str_contains($bulkBody, '_index'));

$failingSearch = new \App\Services\Search\OpenSearchClient(
    'http://search.test:9200',
    'idx',
    fn () => ['ok' => false, 'status' => 503, 'body' => '']
);
check('search: failure returns null', $failingSearch->search(['query' => []]) === null);

$doc = \App\Services\Search\RestaurantSearchDocument::build([
    'id' => 'r-9', 'name' => 'Test', 'rating' => 4.4, 'latitude' => 22.7, 'longitude' => 75.8,
    'is_active' => true, 'is_accepting_orders' => true, 'city_id' => 'c-1',
]);
check('search doc: location mapped', $doc['location'] === ['lat' => 22.7, 'lon' => 75.8]);
check('search doc: open flag', $doc['is_accepting_orders'] === true);

echo "=== Rider exploration tuning ===\n";

$tuner = new \App\Services\Rider\ExplorationTuner();

$low = $tuner->recommend(1000, 50, 12.5);
check('tuning: low share explores more', $low['every'] === 4 && $low['current_pct'] === 5.0);
$inBand = $tuner->recommend(1000, 130, 12.5);
check('tuning: in-band keeps baseline', $inBand['every'] === 8);
$high = $tuner->recommend(1000, 300, 12.5);
check('tuning: high share backs off', $high['every'] === 16);
$cold = $tuner->recommend(10, 2, 12.5);
check('tuning: insufficient data flagged', $cold['insufficient_data'] === true && $cold['every'] === 8);
$clamped = $tuner->recommend(100, 10, 0.0);
check('tuning: target clamped', $clamped['target_pct'] === 1.0);

echo "=== Cutover readiness gate ===\n";

$cutoverGate = new \App\Services\Cutover\CutoverGate();

$blocked = $cutoverGate->evaluate([
    'flags' => ['enabled' => false, 'ready' => false, 'cities' => [], 'restaurants' => []],
    'pricing' => ['total' => 0, 'mismatched' => 0],
    'health' => false,
    'service_token' => false,
    'artifact' => null,
    'projection' => ['last_24h' => 0, 'total' => 0],
]);
check('cutover gate: flags off blocks', $blocked['allowed'] === false);
check('cutover gate: flag blocker listed', in_array('ORDER_COMMAND_CUTOVER_ENABLED is false.', $blocked['blockers'], true));
check('cutover gate: nine checks evaluated', count($blocked['checks']) === 9);

$passResult = $cutoverGate->evaluate([
    'flags' => ['enabled' => true, 'ready' => true, 'cities' => ['city-1'], 'restaurants' => []],
    'pricing' => ['total' => 120, 'mismatched' => 0],
    'health' => true,
    'service_token' => true,
    'artifact' => ['age_days' => 1, 'fails' => 0],
    'projection' => ['last_24h' => 5, 'total' => 5],
]);
check('cutover gate: full evidence allows', $passResult['allowed'] === true);
check('cutover gate: no blockers', $passResult['blockers'] === []);

$mismatch = $cutoverGate->evaluate([
    'flags' => ['enabled' => true, 'ready' => true, 'cities' => ['city-1'], 'restaurants' => []],
    'pricing' => ['total' => 120, 'mismatched' => 1],
    'health' => true,
    'service_token' => true,
    'artifact' => ['age_days' => 1, 'fails' => 0],
    'projection' => ['last_24h' => 5, 'total' => 5],
]);
check('cutover gate: pricing mismatch blocks', $mismatch['allowed'] === false);
check('cutover gate: mismatch detail', str_contains(implode(' ', $mismatch['blockers']), 'pricing mismatch'));

$noSamples = $cutoverGate->evaluate([
    'flags' => ['enabled' => true, 'ready' => true, 'cities' => ['city-1'], 'restaurants' => []],
    'pricing' => ['total' => 0, 'mismatched' => 0],
    'health' => true,
    'service_token' => true,
    'artifact' => ['age_days' => 1, 'fails' => 0],
    'projection' => ['last_24h' => 5, 'total' => 5],
]);
check('cutover gate: no pricing samples blocks', $noSamples['allowed'] === false);

$javaDown = $cutoverGate->evaluate([
    'flags' => ['enabled' => true, 'ready' => true, 'cities' => ['city-1'], 'restaurants' => []],
    'pricing' => ['total' => 120, 'mismatched' => 0],
    'health' => false,
    'service_token' => true,
    'artifact' => ['age_days' => 1, 'fails' => 0],
    'projection' => ['last_24h' => 5, 'total' => 5],
]);
check('cutover gate: java down blocks', $javaDown['allowed'] === false);

$stale = $cutoverGate->evaluate([
    'flags' => ['enabled' => true, 'ready' => true, 'cities' => ['city-1'], 'restaurants' => []],
    'pricing' => ['total' => 120, 'mismatched' => 0],
    'health' => true,
    'service_token' => true,
    'artifact' => ['age_days' => 30, 'fails' => 0],
]);
check('cutover gate: stale certification blocks', $stale['allowed'] === false);

$failedCert = $cutoverGate->evaluate([
    'flags' => ['enabled' => true, 'ready' => true, 'cities' => ['city-1'], 'restaurants' => []],
    'pricing' => ['total' => 120, 'mismatched' => 0],
    'health' => true,
    'service_token' => true,
    'artifact' => ['age_days' => 1, 'fails' => 1],
]);
check('cutover gate: failed certification blocks', $failedCert['allowed'] === false);

$noAllowlist = $cutoverGate->evaluate([
    'flags' => ['enabled' => true, 'ready' => true, 'cities' => [], 'restaurants' => []],
    'pricing' => ['total' => 120, 'mismatched' => 0],
    'health' => true,
    'service_token' => true,
    'artifact' => ['age_days' => 1, 'fails' => 0],
    'projection' => ['last_24h' => 5, 'total' => 5],
]);
check('cutover gate: empty allowlist blocks', $noAllowlist['allowed'] === false);

echo "=== Cutover router (fail-closed) ===\n";

use App\Services\OrderCommandRouter;

$off = new OrderCommandRouter(['cutover_enabled' => false]);
check('router: disabled → laravel', $off->decision('city-1', 'rest-1')['route'] === 'laravel');
check('router: disabled reason', $off->decision('city-1', 'rest-1')['reason'] === 'cutover_disabled');

$notReady = new OrderCommandRouter([
    'cutover_enabled' => true, 'cutover_ready' => false, 'cutover_cities' => ['city-1'],
]);
check('router: enabled but not certified → laravel', $notReady->decision('city-1', null)['route'] === 'laravel');
check('router: not certified reason', $notReady->decision('city-1', null)['reason'] === 'cutover_not_certified');

$ready = new OrderCommandRouter([
    'cutover_enabled' => true, 'cutover_ready' => true,
    'cutover_cities' => ['city-1'], 'cutover_restaurants' => ['rest-9'],
]);
check('router: allowlisted city → java', $ready->decision('city-1', null)['route'] === 'java');
check('router: allowlisted restaurant → java', $ready->decision(null, 'rest-9')['route'] === 'java');
check('router: unlisted → laravel', $ready->decision('city-2', 'rest-2')['route'] === 'laravel');
check('router: csv string parsing', (new OrderCommandRouter([
    'cutover_enabled' => true, 'cutover_ready' => true, 'cutover_cities' => 'city-a, city-b',
]))->shouldRoute('city-b', null) === true);
check('router: empty city is never routed', $ready->shouldRoute(null, null) === false);

echo "\n=== Result: {$pass} PASS, {$fail} FAIL ===\n";

exit($fail > 0 ? 1 : 0);
