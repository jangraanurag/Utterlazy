import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import './style.css'
import App from './App.vue'
import Login from './pages/Login.vue'

import Dashboard from './pages/Dashboard.vue'
import SystemConfigs from './pages/SystemConfigs.vue'
import RescueCenter from './pages/RescueCenter.vue'
import OrderManagement from './pages/OrderManagement.vue'
import CouponManagement from './pages/CouponManagement.vue'
import RiderManagement from './pages/RiderManagement.vue'
import RiderPayouts from './pages/RiderPayouts.vue'
import Banners from './pages/Banners.vue'
import CityManagement from './pages/CityManagement.vue'
import FraudDetection from './pages/FraudDetection.vue'
import NotificationCampaigns from './pages/NotificationCampaigns.vue'
// import PushCampaigns from './pages/PushCampaigns.vue'
import OnboardingApplications from './pages/OnboardingApplications.vue'
import AssignmentLogs from './pages/AssignmentLogs.vue'
import GlobalCategories from './pages/GlobalCategories.vue'
import MenuMapping from './pages/MenuMapping.vue'
import VendorPayments from './pages/VendorPayments.vue'
import InvoicesTaxes from './pages/InvoicesTaxes.vue'
import Reporting from './pages/Reporting.vue'
import PayoutManagement from './pages/PayoutManagement.vue'
import Invoices from './pages/Invoices.vue'
import GSTReports from './pages/GSTReports.vue'
import VenueManagement from './pages/VenueManagement.vue'
import ReviewModeration from './pages/ReviewModeration.vue'
import ComplaintManagement from './pages/ComplaintManagement.vue'
import CustomerManagement from './pages/CustomerManagement.vue'
import RankingMonitor from './pages/RankingMonitor.vue'
import RankingConfig from './pages/RankingConfig.vue'
import AccountingDashboard from './pages/AccountingDashboard.vue'
import EarningsReport from './pages/EarningsReport.vue'
import WarRoom from './pages/WarRoom.vue'
import CampaignManagement from './pages/CampaignManagement.vue'
import AccountManagerManagement from './pages/AccountManagerManagement.vue'
import AmDashboard from './pages/AmDashboard.vue'
import AmCreateAd from './pages/AmCreateAd.vue'
import AmCreateOffer from './pages/AmCreateOffer.vue'
import AmSubmissions from './pages/AmSubmissions.vue'
import RiderKycReview from './pages/RiderKycReview.vue'
import RiderCyclePayouts from './pages/RiderCyclePayouts.vue'
import OrgChartBuilder from './pages/OrgChartBuilder.vue'
import ApprovalThresholds from './pages/ApprovalThresholds.vue'
import ApprovalQueue from './pages/ApprovalQueue.vue'
import SlaDashboard from './pages/SlaDashboard.vue'
import PayoutApprovals from './pages/PayoutApprovals.vue'
import RiderPayoutApprovals from './pages/RiderPayoutApprovals.vue'
import AmMarketingApprovals from './pages/AmMarketingApprovals.vue'
import SupportRefundApprovals from './pages/SupportRefundApprovals.vue'
import FinanceApprovals from './pages/FinanceApprovals.vue'
import GstFilingApprovals from './pages/GstFilingApprovals.vue'
import RiderKycApprovals from './pages/RiderKycApprovals.vue'
import AmAssignmentApprovals from './pages/AmAssignmentApprovals.vue'
import CommandCenter from './pages/CommandCenter.vue'
import PlacementReview from './pages/PlacementReview.vue'
import CityAdFloors from './pages/CityAdFloors.vue'
import WhatsAppOps from './pages/WhatsAppOps.vue'
import Workforce from './pages/Workforce.vue'
import StaffProvision from './pages/StaffProvision.vue'
import IncidentConsole from './pages/IncidentConsole.vue'
import PiiAudit from './pages/PiiAudit.vue'
import ShiftPlanner from './pages/ShiftPlanner.vue'

const routes = [
  { path: '/login', component: Login, meta: { guest: true } },
  { path: '/', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/war-room', component: WarRoom, meta: { requiresAuth: true, layout: 'fullscreen' } },
  { path: '/orders', component: OrderManagement, meta: { requiresAuth: true } },
  { path: '/coupons', component: CouponManagement, meta: { requiresAuth: true } },
  { path: '/configs', component: SystemConfigs, meta: { requiresAuth: true } },
  { path: '/rescue', component: RescueCenter, meta: { requiresAuth: true } },
  { path: '/riders', component: RiderManagement, meta: { requiresAuth: true } },
  { path: '/rider-payouts', component: RiderPayouts, meta: { requiresAuth: true } },
  { path: '/banners', component: Banners, meta: { requiresAuth: true } },
  { path: '/cities', component: CityManagement, meta: { requiresAuth: true } },
  { path: '/fraud', component: FraudDetection, meta: { requiresAuth: true } },
  { path: '/campaigns', component: NotificationCampaigns, meta: { requiresAuth: true } },
  // { path: '/push-campaigns', component: PushCampaigns, meta: { requiresAuth: true } },
  { path: '/onboarding', component: OnboardingApplications, meta: { requiresAuth: true } },
  { path: '/assignment-logs', component: AssignmentLogs, meta: { requiresAuth: true } },
  { path: '/rider-kyc', component: RiderKycReview, meta: { requiresAuth: true } },
  { path: '/rider-cycle-payouts', component: RiderCyclePayouts, meta: { requiresAuth: true } },
  { path: '/org-chart', component: OrgChartBuilder, meta: { requiresAuth: true } },
  { path: '/approval-thresholds', component: ApprovalThresholds, meta: { requiresAuth: true } },
  { path: '/approval-queue', component: ApprovalQueue, meta: { requiresAuth: true } },
  { path: '/sla-dashboard', component: SlaDashboard, meta: { requiresAuth: true } },
  { path: '/payout-approvals', component: PayoutApprovals, meta: { requiresAuth: true } },
  { path: '/rider-payout-approvals', component: RiderPayoutApprovals, meta: { requiresAuth: true } },
  { path: '/am-marketing-approvals', component: AmMarketingApprovals, meta: { requiresAuth: true } },
  { path: '/support-refund-approvals', component: SupportRefundApprovals, meta: { requiresAuth: true } },
  { path: '/finance-approvals', component: FinanceApprovals, meta: { requiresAuth: true } },
  { path: '/gst-filing-approvals', component: GstFilingApprovals, meta: { requiresAuth: true } },
  { path: '/rider-kyc-approvals', component: RiderKycApprovals, meta: { requiresAuth: true } },
  { path: '/am-assignment-approvals', component: AmAssignmentApprovals, meta: { requiresAuth: true } },
  { path: '/categories', component: GlobalCategories, meta: { requiresAuth: true } },
  { path: '/menu-mapping', component: MenuMapping, meta: { requiresAuth: true } },
  { path: '/payments', component: VendorPayments, meta: { requiresAuth: true } },
  { path: '/invoices-taxes', component: InvoicesTaxes, meta: { requiresAuth: true } },
  { path: '/reporting', component: Reporting, meta: { requiresAuth: true } },
  { path: '/payouts', component: PayoutManagement, meta: { requiresAuth: true } },
  { path: '/invoices', component: Invoices, meta: { requiresAuth: true } },
  { path: '/gst-reports', component: GSTReports, meta: { requiresAuth: true } },
  { path: '/restaurants', component: VenueManagement, meta: { requiresAuth: true } },
  { path: '/reviews', component: ReviewModeration, meta: { requiresAuth: true } },
  { path: '/complaints', component: ComplaintManagement, meta: { requiresAuth: true } },
  { path: '/customers', component: CustomerManagement, meta: { requiresAuth: true } },
  { path: '/ranking-monitor', component: RankingMonitor, meta: { requiresAuth: true } },
  { path: '/ranking-config', component: RankingConfig, meta: { requiresAuth: true } },
  { path: '/accounting', component: AccountingDashboard, meta: { requiresAuth: true } },
  { path: '/earnings', component: EarningsReport, meta: { requiresAuth: true } },
  { path: '/marketing-campaigns', component: CampaignManagement, meta: { requiresAuth: true } },
  { path: '/account-managers', component: AccountManagerManagement, meta: { requiresAuth: true } },
  { path: '/am', component: AmDashboard, meta: { requiresAuth: true } },
  { path: '/am/create-ad', component: AmCreateAd, meta: { requiresAuth: true } },
  { path: '/am/create-offer', component: AmCreateOffer, meta: { requiresAuth: true } },
  { path: '/am/submissions', component: AmSubmissions, meta: { requiresAuth: true } },
  { path: '/command-center', component: CommandCenter, meta: { requiresAuth: true } },
  { path: '/placement-review', component: PlacementReview, meta: { requiresAuth: true } },
  { path: '/city-ad-floors', component: CityAdFloors, meta: { requiresAuth: true } },
  { path: '/whatsapp-ops', component: WhatsAppOps, meta: { requiresAuth: true } },
  { path: '/workforce', component: Workforce, meta: { requiresAuth: true } },
  { path: '/staff-provision', component: StaffProvision, meta: { requiresAuth: true } },
  { path: '/incidents', component: IncidentConsole, meta: { requiresAuth: true } },
  { path: '/pii-audit', component: PiiAudit, meta: { requiresAuth: true } },
  { path: '/shift-planner', component: ShiftPlanner, meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('admin_token')
  const user = JSON.parse(localStorage.getItem('admin_user') || '{}')
  const isAm = user.admin_role === 'account_manager'

  if (to.meta.requiresAuth && !token) {
    next('/login')
  } else if (to.meta.guest && token) {
    next(isAm ? '/am' : '/')
  } else if (token && isAm && !to.path.startsWith('/am') && to.path !== '/login') {
    next('/am')
  } else if (token && !isAm && (to.path === '/am' || to.path.startsWith('/am/'))) {
    next('/')
  } else {
    next()
  }
})

createApp(App).use(router).mount('#app')
