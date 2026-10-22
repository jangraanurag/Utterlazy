<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'city_manager' => [
            'restaurants.view', 'restaurants.update',
            'riders.view', 'riders.verify', 'riders.documents',
            'orders.view', 'orders.update',
            'onboarding.view', 'onboarding.approve', 'onboarding.reject',
            'banners.view', 'banners.manage',
            'cities.view', 'cities.manage',
            'categories.view', 'categories.manage',
            'menu-items.view', 'menu-items.update',
            'reviews.view', 'complaints.view', 'complaints.manage',
            'customers.view',
            'ranking.view', 'ranking.manage',
            'approvals.view', 'approvals.act',
            'org.view',
        ],
        'support_agent' => [
            'orders.view', 'orders.update',
            'rescue.view',
            'complaints.view', 'complaints.manage',
            'reviews.view',
            'customers.view',
            'riders.view',
            'approvals.view', 'approvals.act',
            'org.view',
        ],
        'finance_manager' => [
            'orders.view',
            'invoices.view', 'invoices.manage',
            'payments.view', 'payments.manage',
            'gst.view',
            'accounting.view',
            'rider-payouts.view', 'rider-payouts.manage',
            'earnings.view',
            'org.view',
            'approvals.view', 'approvals.act',
        ],
        'account_manager' => [
            'restaurants.view',
            'account-manager.view', 'account-manager.manage',
            'approvals.view', 'approvals.act',
            'org.view',
        ],
        'support_l1' => [
            'orders.view', 'orders.update',
            'rescue.view',
            'complaints.view',
            'customers.view',
            'whatsapp.handoff',
            'org.view',
        ],
        'support_l2' => [
            'orders.view', 'orders.update',
            'rescue.view',
            'complaints.view', 'complaints.manage', 'complaints.refund_originate',
            'customers.view',
            'whatsapp.handoff',
            'approvals.view', 'approvals.act',
            'org.view',
        ],
        'support_lead' => [
            'orders.view',
            'complaints.view',
            'queue.assign',
            'sla.view',
            'incidents.view', 'incidents.manage',
            'shifts.view', 'shifts.manage',
            'sos.view', 'sos.manage',
            'wa.sessions.view',
            'org.view',
        ],
        'city_ops' => [
            'orders.view', 'orders.update',
            'rescue.view', 'rescue.manage',
            'command_center.view',
            'incidents.view', 'incidents.manage',
            'shifts.view', 'shifts.manage',
            'sos.view', 'sos.manage',
            'riders.view',
            'org.view',
        ],
        'rider_ops' => [
            'riders.view', 'riders.verify', 'riders.documents',
            'rider-payouts.view', 'rider-payouts.originate',
            'incidents.view',
            'sos.view', 'sos.manage',
            'approvals.view', 'approvals.act',
            'org.view',
        ],
        'catalog_ops' => [
            'restaurants.view', 'restaurants.update',
            'menu-items.view', 'menu-items.update',
            'categories.view', 'categories.manage',
            'onboarding.view',
            'org.view',
        ],
        'onboarding_agent' => [
            'onboarding.view',
            'restaurants.view',
            'org.view',
        ],
        'ads_ops' => [
            'placements.review',
            'floors.manage',
            'wa.sessions.view',
            'org.view',
            'approvals.view', 'approvals.act',
        ],
        'whatsapp_ops' => [
            'wa.sessions.view',
            'wa.templates.view',
            'wa.assign',
            'incidents.view',
            'shifts.view',
            'org.view',
        ],
        'fraud_analyst' => [
            'orders.view',
            'fraud.view', 'fraud.flag',
            'riders.view',
            'customers.view',
            'org.view',
        ],
        'growth' => [
            'coupons.view',
            'restaurants.view',
            'org.view',
        ],
        'auditor' => [
            'orders.view',
            'restaurants.view',
            'riders.view',
            'customers.view',
            'invoices.view',
            'payments.view',
            'gst.view',
            'accounting.view',
            'fraud.view',
            'approvals.view',
            'wa.sessions.view',
            'incidents.view',
            'shifts.view',
            'sos.view',
            'audit.view',
            'org.view',
        ],
    ];

    public static function roles(): array
    {
        return array_keys(self::ROLE_PERMISSIONS);
    }

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_admin) {
            return response()->json(['error' => 'Unauthorized — admin access required'], 403);
        }

        if ($permission === null) {
            return $next($request);
        }

        $role = $user->admin_role ?? 'super_admin';
        $allowed = self::ROLE_PERMISSIONS[$role] ?? [];

        if (in_array('*', $allowed) || in_array($permission, $allowed)) {
            return $next($request);
        }

        return response()->json([
            'error' => "Forbidden — role '{$role}' lacks permission '{$permission}'",
        ], 403);
    }
}
