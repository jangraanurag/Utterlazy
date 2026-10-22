<template>
  <div class="min-h-screen bg-background text-on-surface flex">
    <!-- Sidebar -->
    <aside class="w-72 bg-surface border-r border-white/5 flex flex-col h-screen fixed">
      <div class="p-8 border-b border-white/5">
        <div class="mb-1">
          <img src="/logo.png" alt="UtterLazy" class="h-10 mx-auto object-contain" />
        </div>
        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-primary">Command Center</span>
      </div>
      
      <nav class="flex-1 py-8 px-6 space-y-2 overflow-y-auto no-scrollbar">
        <template v-if="isAm">
          <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-secondary font-bold mb-4 opacity-80">Account Manager</p>
          
          <router-link to="/am" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">storefront</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">My Restaurants</span>
          </router-link>

          <router-link to="/reporting" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">bar_chart</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Reporting</span>
          </router-link>
          
          <router-link to="/am/create-ad" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">campaign</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Create Ad</span>
          </router-link>
          
          <router-link to="/am/create-offer" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">local_offer</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Create Offer</span>
          </router-link>
          
          <router-link to="/am/submissions" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">fact_check</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">My Submissions</span>
          </router-link>
        </template>

        <template v-if="!isAm">
          <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-on-surface-variant font-bold mb-4 opacity-50">Intelligence</p>
          
          <router-link to="/" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">dashboard</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Overview</span>
          </router-link>
          
          <router-link to="/orders" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">receipt_long</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Live Missions</span>
          </router-link>

          <router-link to="/rescue" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">healing</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Rescue Center</span>
          </router-link>

          <router-link to="/reporting" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">bar_chart</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Reporting</span>
          </router-link>

          <div class="my-6 border-t border-white/5"></div>
          <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-secondary font-bold mb-4 opacity-80">Logistics Ops</p>

          <router-link to="/riders" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Rider Fleet</span>
          </router-link>

          <router-link to="/rider-kyc" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">verified</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">KYC Review</span>
          </router-link>

          <router-link to="/rider-cycle-payouts" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">calendar_month</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Cycle Payouts</span>
          </router-link>

          <router-link to="/rider-payouts" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">payments</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Vault Payouts</span>
          </router-link>

          <router-link to="/assignment-logs" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">history</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Assignment Logs</span>
          </router-link>

          <router-link to="/fraud" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">shield</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Fraud Detection</span>
          </router-link>

          <div class="my-6 border-t border-white/5"></div>
          <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-secondary font-bold mb-4 opacity-80">Venues</p>

          <router-link to="/restaurants" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">storefront</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Venue Management</span>
          </router-link>

          <router-link to="/onboarding" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">app_registration</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Onboarding</span>
          </router-link>

          <router-link to="/reviews" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">rate_review</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Reviews</span>
          </router-link>

          <router-link to="/complaints" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">feedback</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Complaints</span>
          </router-link>

          <router-link to="/menu-mapping" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">link</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Menu Mapping</span>
          </router-link>

          <div class="my-6 border-t border-white/5"></div>
          <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-secondary font-bold mb-4 opacity-80">Accounting</p>

          <router-link to="/accounting" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">analytics</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Financial Overview</span>
          </router-link>

          <router-link to="/payments" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">account_balance</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Vendor Payments</span>
          </router-link>

          <router-link to="/invoices-taxes" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">receipt_long</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Invoices & Taxes</span>
          </router-link>

          <div class="my-6 border-t border-white/5"></div>
          <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-secondary font-bold mb-4 opacity-80">Org & Approvals</p>

          <router-link to="/org-chart" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">account_tree</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Org Chart</span>
          </router-link>

          <router-link to="/approval-thresholds" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">rule</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">DOA Matrix</span>
          </router-link>

          <router-link to="/approval-queue" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">pending_actions</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Approval Queue</span>
          </router-link>

          <router-link to="/sla-dashboard" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">timer</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">SLA Watch</span>
          </router-link>

          <router-link to="/payout-approvals" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">verified_user</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Payout Approvals</span>
          </router-link>

          <router-link to="/rider-payout-approvals" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">delivery_dining</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Rider Pay Approvals</span>
          </router-link>

          <router-link to="/rider-kyc-approvals" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">badge</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Rider KYC Approvals</span>
          </router-link>

          <router-link to="/am-marketing-approvals" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">campaign</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">AM Marketing Approvals</span>
          </router-link>

          <router-link to="/am-assignment-approvals" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">assignment_ind</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">AM Restaurant Assignment</span>
          </router-link>

          <router-link to="/support-refund-approvals" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">support_agent</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Support Refund Approvals</span>
          </router-link>

          <router-link to="/finance-approvals" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">request_quote</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Invoice & Recovery Approvals</span>
          </router-link>

          <router-link to="/gst-filing-approvals" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">account_balance</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">GST Filing</span>
          </router-link>

          <router-link to="/payouts" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">payments</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Payout Cycles</span>
          </router-link>

          <router-link to="/invoices" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">receipt</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Invoices</span>
          </router-link>

          <router-link to="/gst-reports" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">description</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">GST Reports</span>
          </router-link>

          <router-link to="/earnings" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">trending_up</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Platform Earnings</span>
          </router-link>

          <router-link to="/coupons" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">confirmation_number</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Coupons</span>
          </router-link>

          <router-link to="/marketing-campaigns" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">local_offer</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Discount Campaigns</span>
          </router-link>

          <router-link to="/account-managers" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Account Managers</span>
          </router-link>

          <div class="my-6 border-t border-white/5"></div>
          <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-secondary font-bold mb-4 opacity-80">Customers</p>

          <router-link to="/customers" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">people</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Customers</span>
          </router-link>

          <div class="my-6 border-t border-white/5"></div>
          <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-secondary font-bold mb-4 opacity-80">Algorithms</p>

          <router-link to="/ranking-monitor" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">leaderboard</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Ranking Monitor</span>
          </router-link>

          <router-link to="/ranking-config" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">tune</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Ranking Config</span>
          </router-link>

          <div class="my-6 border-t border-white/5"></div>
          <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-on-surface-variant font-bold mb-4 opacity-50">System Configuration</p>

          <router-link to="/categories" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">category</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Global Categories</span>
          </router-link>

          <router-link to="/banners" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">view_carousel</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Banners</span>
          </router-link>

          <router-link to="/cities" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">location_city</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Cities</span>
          </router-link>

          <router-link to="/campaigns" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">campaign</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Notifications</span>
          </router-link>

          <router-link to="/configs" class="nav-link" active-class="nav-link-active">
            <span class="material-symbols-outlined text-[20px]">settings_applications</span>
            <span class="font-display font-bold uppercase tracking-widest text-[11px]">Global Config</span>
          </router-link>

          <template v-if="canSeeOps">
            <div class="my-6 border-t border-white/5"></div>
            <p class="px-4 text-[9px] uppercase tracking-[0.2em] text-secondary font-bold mb-4 opacity-80">WhatsApp &amp; Ops</p>

            <router-link v-if="can('command_center.view')" to="/command-center" class="nav-link" active-class="nav-link-active">
              <span class="material-symbols-outlined text-[20px]">monitoring</span>
              <span class="font-display font-bold uppercase tracking-widest text-[11px]">Command Center</span>
            </router-link>

            <router-link v-if="can('placements.review')" to="/placement-review" class="nav-link" active-class="nav-link-active">
              <span class="material-symbols-outlined text-[20px]">ad_units</span>
              <span class="font-display font-bold uppercase tracking-widest text-[11px]">Placement Review</span>
            </router-link>

            <router-link v-if="can('floors.manage')" to="/city-ad-floors" class="nav-link" active-class="nav-link-active">
              <span class="material-symbols-outlined text-[20px]">price_change</span>
              <span class="font-display font-bold uppercase tracking-widest text-[11px]">City Ad Floors</span>
            </router-link>

            <router-link v-if="can('wa.sessions.view')" to="/whatsapp-ops" class="nav-link" active-class="nav-link-active">
              <span class="material-symbols-outlined text-[20px]">forum</span>
              <span class="font-display font-bold uppercase tracking-widest text-[11px]">WhatsApp Ops</span>
            </router-link>

            <router-link v-if="can('audit.view')" to="/pii-audit" class="nav-link" active-class="nav-link-active">
              <span class="material-symbols-outlined text-[20px]">policy</span>
              <span class="font-display font-bold uppercase tracking-widest text-[11px]">PII Audit</span>
            </router-link>

            <router-link v-if="can('shifts.view')" to="/shift-planner" class="nav-link" active-class="nav-link-active">
              <span class="material-symbols-outlined text-[20px]">calendar_month</span>
              <span class="font-display font-bold uppercase tracking-widest text-[11px]">Shift Planner</span>
            </router-link>

            <router-link v-if="can('incidents.view')" to="/incidents" class="nav-link" active-class="nav-link-active">
              <span class="material-symbols-outlined text-[20px]">crisis_alert</span>
              <span class="font-display font-bold uppercase tracking-widest text-[11px]">Incident Console</span>
            </router-link>

            <router-link v-if="can('queue.assign')" to="/workforce" class="nav-link" active-class="nav-link-active">
              <span class="material-symbols-outlined text-[20px]">groups</span>
              <span class="font-display font-bold uppercase tracking-widest text-[11px]">Workforce</span>
            </router-link>

            <router-link v-if="isSuperAdmin" to="/staff-provision" class="nav-link" active-class="nav-link-active">
              <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
              <span class="font-display font-bold uppercase tracking-widest text-[11px]">Staff Provision</span>
            </router-link>
          </template>
        </template>
      </nav>

      <div class="p-6 border-t border-white/5 bg-surface-variant/20">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded-xl sovereign-gradient flex items-center justify-center font-display font-bold text-on-primary">{{ userInitials }}</div>
          <div>
            <p class="text-[11px] font-bold text-white uppercase tracking-widest">{{ userRoleLabel }}</p>
            <p class="text-[9px] text-on-surface-variant uppercase tracking-widest">{{ userRoleSubtitle }}</p>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 ml-72 min-h-screen p-12">
      <router-view></router-view>
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const adminUser = computed(() => {
  try { return JSON.parse(localStorage.getItem('admin_user') || '{}') } catch { return {} }
})
const isAm = computed(() => adminUser.value.admin_role === 'account_manager')
const isSuperAdmin = computed(() => adminUser.value.admin_role === 'super_admin')

// Mirrors catalog AdminAuth for Wave 1 ops screens. Sidebar is a convenience;
// the API enforces the same permissions.
const ROLE_PERMISSIONS = {
  super_admin: ['*'],
  city_ops: ['command_center.view', 'incidents.view', 'shifts.view'],
  support_lead: ['queue.assign', 'incidents.view', 'shifts.view'],
  ads_ops: ['placements.review', 'floors.manage'],
  whatsapp_ops: ['wa.sessions.view', 'incidents.view', 'shifts.view'],
  support_l1: ['wa.sessions.view'],
  support_l2: ['wa.sessions.view'],
  auditor: ['command_center.view', 'wa.sessions.view', 'incidents.view', 'audit.view', 'shifts.view'],
}

const can = (permission) => {
  const role = adminUser.value.admin_role
  const allowed = ROLE_PERMISSIONS[role] || []
  return allowed.includes('*') || allowed.includes(permission)
}

const canSeeOps = computed(() => {
  const role = adminUser.value.admin_role
  return isSuperAdmin.value || !!ROLE_PERMISSIONS[role]
})
const userName = computed(() => adminUser.value.name || 'Admin')
const userInitials = computed(() => {
  const name = userName.value
  const parts = name.split(' ')
  return parts.length > 1 ? (parts[0][0] + parts[1][0]).toUpperCase() : parts[0].slice(0, 2).toUpperCase()
})
const userRoleLabel = computed(() => isAm.value ? 'Account Manager' : 'Super Admin')
const userRoleSubtitle = computed(() => isAm.value ? 'Operations' : 'System Master')
</script>

<style>
.nav-link {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.875rem 1rem;
  border-radius: 0.75rem;
  transition: all 0.3s;
  color: var(--color-on-surface-variant);
}
.nav-link:hover {
  color: white;
  background: rgba(255,255,255,0.05);
}
.nav-link-active {
  background: rgba(255,107,0,0.1);
  color: var(--color-primary);
  border: 1px solid rgba(255,107,0,0.2);
  box-shadow: 0 0 20px rgba(255,107,0,0.05);
}
</style>
