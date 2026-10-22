// @ts-nocheck
const API_BASE = 'https://catalog.utterlazy.com/api/admin'

const getToken = () => localStorage.getItem('admin_token')

const authHeaders = () => {
  const token = getToken()
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' }
  if (token) headers['Authorization'] = `Bearer ${token}`
  return headers
}

export const adminLogin = async (email, password) => {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ email, password })
  })
  const json = await res.json()
  if (!res.ok) throw json
  localStorage.setItem('admin_token', json.token)
  return json
}

export const adminLogout = async () => {
  try {
    await fetch('https://catalog.utterlazy.com/api/auth/logout', { method: 'POST', headers: authHeaders() })
  } catch { /* ignore */ }
  localStorage.removeItem('admin_token')
  localStorage.removeItem('admin_user')
}

export const getConfigs = async () => {
  const res = await fetch(`${API_BASE}/configs`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch configs')
  return await res.json()
}

export const updateConfigs = async (configsArray) => {
  const res = await fetch(`${API_BASE}/configs`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({ configs: configsArray })
  })
  if (!res.ok) throw new Error('Failed to update configs')
  return await res.json()
}

export const getRescueOrders = async () => {
  const res = await fetch(`${API_BASE}/rescue-orders`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch rescue orders')
  return await res.json()
}

export const getStats = async () => {
  const res = await fetch(`${API_BASE}/stats`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch stats')
  return await res.json()
}

export const getLiveStats = async () => {
  const res = await fetch(`${API_BASE}/live-stats`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch live stats')
  return await res.json()
}

export const getOrderMap = async () => {
  const res = await fetch(`${API_BASE}/order-map`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch order map')
  return await res.json()
}

export const getAllOrders = async (status = 'all', page = 1) => {
  const params = new URLSearchParams({ page })
  if (status !== 'all') params.append('status', status)
  const res = await fetch(`${API_BASE}/orders?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch orders')
  return await res.json()
}

export const adminUpdateOrderStatus = async (orderId, status) => {
  const res = await fetch(`${API_BASE}/orders/${orderId}/status`, {
    method: 'PATCH',
    headers: authHeaders(),
    body: JSON.stringify({ status })
  })
  if (!res.ok) throw new Error('Failed to update status')
  return await res.json()
}

export const getCoupons = async () => {
  const res = await fetch(`${API_BASE}/coupons`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch coupons')
  return await res.json()
}

export const createCoupon = async (data) => {
  const res = await fetch(`${API_BASE}/coupons`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const updateCoupon = async (id, data) => {
  const res = await fetch(`${API_BASE}/coupons/${id}`, {
    method: 'PUT',
    headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) throw new Error('Failed to update coupon')
  return await res.json()
}

export const deleteCoupon = async (id) => {
  const res = await fetch(`${API_BASE}/coupons/${id}`, { method: 'DELETE', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to delete coupon')
  return await res.json()
}

// ─── Onboarding Applications ───
export const getOnboardingApps = async (status = 'all', page = 1) => {
  const params = new URLSearchParams({ page })
  if (status !== 'all') params.append('status', status)
  const res = await fetch(`${API_BASE}/onboarding/applications?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch applications')
  return await res.json()
}

export const approveOnboardingApp = async (id) => {
  const res = await fetch(`${API_BASE}/onboarding/applications/${id}/approve`, {
    method: 'PATCH', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to approve')
  return await res.json()
}

export const rejectOnboardingApp = async (id, reason) => {
  const res = await fetch(`${API_BASE}/onboarding/applications/${id}/reject`, {
    method: 'PATCH', headers: authHeaders(),
    body: JSON.stringify({ reason })
  })
  if (!res.ok) throw new Error('Failed to reject')
  return await res.json()
}

export const getRiders = async (status = '', page = 1) => {
  const params = new URLSearchParams({ page })
  if (status) params.append('status', status)
  const res = await fetch(`${API_BASE}/riders?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch riders')
  return await res.json()
}

export const createRider = async (data) => {
  const res = await fetch(`${API_BASE}/riders`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const verifyRider = async (id) => {
  const res = await fetch(`${API_BASE}/riders/${id}/verify`, {
    method: 'PATCH',
    headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to update rider')
  return await res.json()
}

export const getRiderPayouts = async (filters = {}) => {
  const params = new URLSearchParams()
  if (filters.page) params.append('page', filters.page)
  if (filters.rider_id) params.append('rider_id', filters.rider_id)
  if (filters.settled !== undefined) params.append('settled', filters.settled)
  if (filters.date) params.append('date', filters.date)
  const res = await fetch(`${API_BASE}/rider-payouts?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch payouts')
  return await res.json()
}

export const settlePayouts = async (riderId = null) => {
  const body = riderId ? { rider_id: riderId } : {}
  const res = await fetch(`${API_BASE}/rider-payouts/settle`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(body)
  })
  if (!res.ok) throw new Error('Failed to settle payouts')
  return await res.json()
}

// ─── Rider KYC / Document Review (§6) ───
export const getRiderDocuments = async (riderId) => {
  const res = await fetch(`${API_BASE}/riders/${riderId}/documents`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch rider documents')
  return await res.json()
}

export const approveRiderDocument = async (riderId, docId) => {
  const res = await fetch(`${API_BASE}/riders/${riderId}/documents/${docId}/approve`, {
    method: 'PATCH', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to approve document')
  return await res.json()
}

export const rejectRiderDocument = async (riderId, docId, reason) => {
  const res = await fetch(`${API_BASE}/riders/${riderId}/documents/${docId}/reject`, {
    method: 'PATCH', headers: authHeaders(), body: JSON.stringify({ rejection_reason: reason })
  })
  if (!res.ok) throw new Error('Failed to reject document')
  return await res.json()
}

export const verifyRiderDocumentsFinal = async (riderId) => {
  const res = await fetch(`${API_BASE}/riders/${riderId}/verify-documents`, {
    method: 'PATCH', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to finalize verification')
  return await res.json()
}

// ─── Daily Rider Payout Cycles (§5) ───
export const getRiderPayoutCycles = async (filters = {}) => {
  const params = new URLSearchParams()
  if (filters.page) params.append('page', filters.page)
  if (filters.status) params.append('status', filters.status)
  if (filters.rider_id) params.append('rider_id', filters.rider_id)
  if (filters.date_from) params.append('date_from', filters.date_from)
  if (filters.date_to) params.append('date_to', filters.date_to)
  const res = await fetch(`${API_BASE}/rider-payout-cycles?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch payout cycles')
  return await res.json()
}

export const payRiderPayoutCycle = async (cycleId, utrNumber, paymentMode = 'bank_transfer', paymentNotes = '') => {
  const res = await fetch(`${API_BASE}/rider-payout-cycles/${cycleId}/pay`, {
    method: 'POST', headers: authHeaders(), body: JSON.stringify({
      utr_number: utrNumber, payment_mode: paymentMode, payment_notes: paymentNotes,
    })
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const getAssignmentLogs = async (filters = {}) => {
  const params = new URLSearchParams()
  if (filters.page) params.append('page', filters.page)
  if (filters.order_id) params.append('order_id', filters.order_id)
  if (filters.rider_id) params.append('rider_id', filters.rider_id)
  if (filters.status) params.append('status', filters.status)
  const res = await fetch(`${API_BASE}/assignment-logs?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch logs')
  return await res.json()
}

// ─── Global Categories CRUD ───
export const getGlobalCategories = async () => {
  const res = await fetch(`${API_BASE}/global-categories`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch global categories')
  return await res.json()
}

export const createGlobalCategory = async (data) => {
  const res = await fetch(`${API_BASE}/global-categories`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const updateGlobalCategory = async (id, data) => {
  const res = await fetch(`${API_BASE}/global-categories/${id}`, {
    method: 'PUT',
    headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const deleteGlobalCategory = async (id) => {
  const res = await fetch(`${API_BASE}/global-categories/${id}`, { method: 'DELETE', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to delete global category')
  return await res.json()
}

// ─── Admin Menu Item Mapping ───
export const getAdminMenuItems = async () => {
  const res = await fetch(`${API_BASE}/menu-items`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch menu items')
  return await res.json()
}

export const updateAdminMenuItem = async (id, data) => {
  const res = await fetch(`${API_BASE}/menu-items/${id}`, {
    method: 'PUT',
    headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

// ─── Fraud Detection ───
export const getFraudLogs = async () => {
  const res = await fetch(`${API_BASE}/fraud/logs`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch fraud logs')
  return await res.json()
}

export const reviewFraudLog = async (id) => {
  const res = await fetch(`${API_BASE}/fraud/logs/${id}/review`, {
    method: 'PATCH', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to review')
  return await res.json()
}

// ─── Restaurant Management ───
export const getRestaurants = async (search = '', page = 1) => {
  const params = new URLSearchParams({ page })
  if (search) params.append('search', search)
  const res = await fetch(`${API_BASE}/restaurants?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch restaurants')
  return await res.json()
}

export const getRestaurantDetail = async (id) => {
  const res = await fetch(`${API_BASE}/restaurants/${id}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch restaurant')
  return await res.json()
}

export const updateRestaurant = async (id, data) => {
  const res = await fetch(`${API_BASE}/restaurants/${id}`, {
    method: 'PATCH', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) throw new Error('Failed to update restaurant')
  return await res.json()
}

// ─── Review Moderation ───
export const getReviews = async (restaurantId = '', page = 1) => {
  const params = new URLSearchParams({ page })
  if (restaurantId) params.append('restaurant_id', restaurantId)
  const res = await fetch(`${API_BASE}/reviews?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch reviews')
  return await res.json()
}

// ─── Complaint Management ───
export const getComplaints = async (status = 'all', page = 1) => {
  const params = new URLSearchParams({ page })
  if (status !== 'all') params.append('status', status)
  const res = await fetch(`${API_BASE}/complaints?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch complaints')
  return await res.json()
}

// ─── Customer Management ───
export const getCustomers = async (search = '', page = 1) => {
  const params = new URLSearchParams({ page })
  if (search) params.append('search', search)
  const res = await fetch(`${API_BASE}/customers?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch customers')
  return await res.json()
}

// ─── Vendor Payments ───
export const getVendorPayments = async (page = 1) => {
  const res = await fetch(`${API_BASE}/payments?${new URLSearchParams({ page })}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch payments')
  return await res.json()
}

export const approveVendorPayment = async (id) => {
  const res = await fetch(`${API_BASE}/payments/${id}/approve`, { method: 'PATCH', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to approve')
  return await res.json()
}

export const completeVendorPayment = async (id) => {
  const res = await fetch(`${API_BASE}/payments/${id}/complete`, { method: 'POST', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to complete')
  return await res.json()
}

export const generateWeeklyPayments = async () => {
  const res = await fetch(`${API_BASE}/payments/generate-weekly`, { method: 'POST', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to generate')
  return await res.json()
}

// ─── Invoices ───
export const getInvoices = async (page = 1) => {
  const res = await fetch(`${API_BASE}/invoices?${new URLSearchParams({ page })}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch invoices')
  return await res.json()
}

export const approveInvoice = async (id) => {
  const res = await fetch(`${API_BASE}/invoices/${id}/approve`, { method: 'PATCH', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to approve')
  return await res.json()
}

export const markInvoicePaid = async (id) => {
  const res = await fetch(`${API_BASE}/invoices/${id}/mark-paid`, { method: 'PATCH', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to mark paid')
  return await res.json()
}

export const cancelInvoice = async (id) => {
  const res = await fetch(`${API_BASE}/invoices/${id}/cancel`, { method: 'PATCH', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to cancel')
  return await res.json()
}

export const generateInvoices = async (data = null) => {
  const options = {
    method: 'POST',
    headers: authHeaders(),
  }
  if (data) {
    options.body = JSON.stringify(data)
  }
  const res = await fetch(`${API_BASE}/invoices/generate`, options)
  const json = await res.json()
  if (!res.ok) throw new Error(json.error || json.message || 'Failed to generate')
  return json
}

// ─── GST Reports ───
export const getGstRestaurants = async () => {
  const res = await fetch(`${API_BASE}/gst/restaurants`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch restaurants')
  return await res.json()
}

// ─── City Management ───
export const getCities = async () => {
  const res = await fetch(`${API_BASE}/cities`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch cities')
  return await res.json()
}

export const createCity = async (data) => {
  const res = await fetch(`${API_BASE}/cities`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const updateCity = async (id, data) => {
  const res = await fetch(`${API_BASE}/cities/${id}`, {
    method: 'PUT', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const deleteCity = async (id) => {
  const res = await fetch(`${API_BASE}/cities/${id}`, { method: 'DELETE', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to delete')
  return await res.json()
}

export const getGstReport = async (restaurantId, year, month) => {
  const res = await fetch(`${API_BASE}/gst/report/${restaurantId}/${year}/${month}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch GST report')
  return await res.json()
}

// ─── Banner Management ───
export const getBanners = async () => {
  const res = await fetch(`${API_BASE}/banners`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch banners')
  return await res.json()
}

export const createBanner = async (data) => {
  const res = await fetch(`${API_BASE}/banners`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const updateBanner = async (id, data) => {
  const res = await fetch(`${API_BASE}/banners/${id}`, {
    method: 'PUT', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const deleteBanner = async (id) => {
  const res = await fetch(`${API_BASE}/banners/${id}`, { method: 'DELETE', headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to delete')
  return await res.json()
}

// ─── Download Utility ───────────────────────────────────────────────────
export const downloadBlob = async (url, filename) => {
  const res = await fetch(url, { headers: authHeaders() })
  if (!res.ok) throw new Error(`Download failed (${res.status})`)
  const blob = await res.blob()
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  setTimeout(() => URL.revokeObjectURL(link.href), 10000)
  return filename
}

export const downloadGstExcel = async (restaurantId, year, month) => {
  return downloadBlob(
    `${API_BASE}/gst/report/${restaurantId}/${year}/${month}`,
    `GST_Report_${restaurantId}_${year}_${month}.xlsx`
  )
}

export const downloadPaymentsExcel = async () => {
  const res = await fetch(`${API_BASE}/payments`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch payments')
  const json = await res.json()
  const payments = json.payments || json.data || json
  const csvRows = [['Payment ID', 'Restaurant', 'Period', 'Total Orders', 'Gross Revenue', 'Commission %', 'Commission Amt', 'Refunds', 'Net Payable', 'Status']]
  for (const p of payments) {
    csvRows.push([p.id, p.restaurant?.name || p.restaurant_name, p.payment_period, p.total_orders, p.total_order_value, p.commission_rate, p.total_commission, p.total_refunds, p.net_payable, p.status])
  }
  const csv = csvRows.map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `Payments_${new Date().toISOString().slice(0, 10)}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  return link.download
}

export const downloadInvoicesExcel = async () => {
  const res = await fetch(`${API_BASE}/invoices`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch invoices')
  const json = await res.json()
  const invoices = json.invoices || json.data || json
  const csvRows = [['Invoice #', 'Restaurant', 'Type', 'Period Start', 'Period End', 'Amount', 'GST Rate', 'GST Amount', 'Total', 'Status']]
  for (const inv of invoices) {
    csvRows.push([inv.invoice_number, inv.restaurant?.name || '', inv.type, inv.period_start, inv.period_end, inv.amount, inv.gst_rate, inv.gst_amount, inv.total_amount, inv.status])
  }
  const csv = csvRows.map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `Invoices_${new Date().toISOString().slice(0, 10)}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  return link.download
}

export const downloadRiderPayoutsExcel = async (filters = {}) => {
  const params = new URLSearchParams()
  if (filters.page) params.append('page', filters.page)
  if (filters.rider_id) params.append('rider_id', filters.rider_id)
  if (filters.settled !== undefined) params.append('settled', filters.settled)
  if (filters.date) params.append('date', filters.date)
  const res = await fetch(`${API_BASE}/rider-payouts?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch rider payouts')
  const json = await res.json()
  const payouts = json.payouts || json.data || json
  const csvRows = [['Rider', 'Order', 'Base', 'Distance', 'Surge', 'Wait', 'Tip', 'Bonus', 'Penalty', 'COD', 'Net', 'Status']]
  for (const p of payouts) {
    csvRows.push([p.rider?.name || p.rider_name, p.order_id, p.base_pay, p.distance_pay, p.surge_pay, p.waiting_pay, p.tip_amount, p.bonus_amount, p.penalty_amount, p.cod_amount, p.net_amount, p.settled ? 'Settled' : 'Unsettled'])
  }
  const csv = csvRows.map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `RiderPayouts_${new Date().toISOString().slice(0, 10)}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  return link.download
}

export const getAccountingOverview = async () => {
  const res = await fetch(`${API_BASE}/stats`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch stats')
  return await res.json()
}

// ─── Ranking Monitor & Config ───
export const getRankingMonitor = async () => {
  const res = await fetch(`${API_BASE}/ranking/monitor`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch ranking data')
  return await res.json()
}

export const getRankingConfig = async () => {
  const res = await fetch(`${API_BASE}/ranking/config`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch ranking config')
  return await res.json()
}

export const saveRankingConfig = async (config) => {
  const res = await fetch(`${API_BASE}/ranking/config`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(config)
  })
  if (!res.ok) throw new Error('Failed to save ranking config')
  return await res.json()
}

// ─── Campaign Management ───
export const adminGetCampaigns = async () => {
  const res = await fetch(`${API_BASE}/campaigns`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch campaigns')
  const json = await res.json()
  return json.campaigns || []
}

export const adminCreateCampaign = async (data) => {
  const res = await fetch(`${API_BASE}/campaigns`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Account Manager ───
export const adminGetAccountManagers = async () => {
  const res = await fetch(`${API_BASE}/account-managers`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch')
  return (await res.json()).account_managers || []
}

export const adminCreateAccountManager = async (data) => {
  const res = await fetch(`${API_BASE}/account-managers`, {
    method: 'POST', headers: authHeaders(), body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const adminUpdateAccountManager = async (id, data) => {
  const res = await fetch(`${API_BASE}/account-managers/${id}`, {
    method: 'PATCH', headers: authHeaders(), body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const adminAssignRestaurant = async (accountManagerId, restaurantId) => {
  const res = await fetch(`${API_BASE}/account-managers/assign`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ account_manager_id: accountManagerId, restaurant_id: restaurantId })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const adminUnassignRestaurant = async (assignmentId) => {
  const res = await fetch(`${API_BASE}/account-managers/assignments/${assignmentId}`, {
    method: 'DELETE', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed')
  return res.json()
}

export const adminGetAssignments = async () => {
  const res = await fetch(`${API_BASE}/account-managers/assignments`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed')
  return (await res.json()).assignments || {}
}

export const adminGetAvailableRestaurants = async () => {
  const res = await fetch(`${API_BASE}/account-managers/available-restaurants`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed')
  return (await res.json()).restaurants || []
}

export const adminUpdateCampaign = async (id, data) => {
  const res = await fetch(`${API_BASE}/campaigns/${id}`, {
    method: 'PUT', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Invoice & GSTR1 Download ────────────────────────────────────────────
export const downloadInvoicePdf = (id) => downloadBlob(
  `${API_BASE}/invoices/${id}/download-pdf`,
  `Invoice_${id.slice(0,8)}.pdf`
)

export const downloadInvoiceBundle = (restaurantId, type, year, month) => downloadBlob(
  `${API_BASE}/invoices/download-bundle?${new URLSearchParams({ restaurant_id: restaurantId, type, year, month })}`,
  `Invoices_${type}_${year}_${month}.zip`
)

export const downloadGstr1Xlsx = (restaurantId, year, month) => downloadBlob(
  `${API_BASE}/invoices/gstr1-report?${new URLSearchParams({ restaurant_id: restaurantId, year, month })}`,
  `GSTR1_${restaurantId.slice(0,8)}_${year}_${month}.xlsx`
)

export const listGstRestaurants = async () => {
  const res = await fetch(`${API_BASE}/gst/restaurants`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed')
  return (await res.json()).restaurants || []
}

// ─── Recovery Management ─────────────────────────────────────────────────
export const getRecoveries = async (filters = {}) => {
  const params = new URLSearchParams()
  if (filters.restaurant_id) params.append('restaurant_id', filters.restaurant_id)
  if (filters.status) params.append('status', filters.status)
  const res = await fetch(`${API_BASE}/recoveries?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed')
  return await res.json()
}

export const createRecovery = async (data) => {
  const res = await fetch(`${API_BASE}/recoveries`, {
    method: 'POST', headers: authHeaders(), body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const updateRecovery = async (id, data) => {
  const res = await fetch(`${API_BASE}/recoveries/${id}`, {
    method: 'PATCH', headers: authHeaders(), body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Payout Cycles (Finance Module) ───
export const searchRestaurantsForPayout = async (q) => {
  const params = new URLSearchParams({ q })
  const res = await fetch(`${API_BASE}/payouts/search?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to search restaurants')
  return await res.json()
}

export const getPayoutCycles = async (filters = {}) => {
  const params = new URLSearchParams()
  const allowed = ['page', 'per_page', 'status', 'from_date', 'to_date', 'restaurant_id']
  for (const key of allowed) {
    if (filters[key]) params.append(key, filters[key])
  }
  const res = await fetch(`${API_BASE}/payouts?${params}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch cycles')
  return await res.json()
}

export const getPayoutCycle = async (id) => {
  const res = await fetch(`${API_BASE}/payouts/${id}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch cycle')
  return await res.json()
}

export const generatePayoutCycle = async (restaurantId, cycleStart, cycleEnd) => {
  const res = await fetch(`${API_BASE}/payouts/generate`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({ restaurant_id: restaurantId, cycle_start: cycleStart, cycle_end: cycleEnd })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const markPayoutPaid = async (cycleId, data) => {
  const res = await fetch(`${API_BASE}/payouts/${cycleId}/mark-paid`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const addPayoutDeduction = async (cycleId, data) => {
  const res = await fetch(`${API_BASE}/payouts/${cycleId}/deductions`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const addPayoutAddition = async (cycleId, data) => {
  const res = await fetch(`${API_BASE}/payouts/${cycleId}/additions`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const downloadPayoutsExcel = async () => {
  const res = await fetch(`${API_BASE}/payouts?per_page=5000`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch')
  const json = await res.json()
  const cycles = json.data || []
  const csvRows = [['Restaurant', 'Cycle Start', 'Cycle End', 'Gross', 'Deductions', 'Additions', 'Net Payable', 'Status', 'UTR', 'Payment Date']]
  for (const c of cycles) {
    csvRows.push([
      c.restaurant?.name || c.restaurant_id,
      c.cycle_start?.slice(0,10),
      c.cycle_end?.slice(0,10),
      c.gross_order_value,
      c.total_deductions,
      c.total_additions,
      c.net_payable,
      c.status,
      c.utr_number || '',
      c.payment_date?.slice(0,10),
    ])
  }
  const csv = csvRows.map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `PayoutCycles_${new Date().toISOString().slice(0,10)}.csv`
  document.body.appendChild(link); link.click()
  document.body.removeChild(link)
  return link.download
}

// ─── Account Manager Self-Service ───
const AM_API_BASE = 'https://catalog.utterlazy.com/api/account-manager'

export const amGetMyRestaurants = async () => {
  const res = await fetch(`${AM_API_BASE}/restaurants`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed')
  return (await res.json()).restaurants || []
}

export const amCreateAd = async (data) => {
  const res = await fetch(`${AM_API_BASE}/ads`, {
    method: 'POST', headers: authHeaders(), body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const amCreateOffer = async (data) => {
  const res = await fetch(`${AM_API_BASE}/offers`, {
    method: 'POST', headers: authHeaders(), body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const amGetMySubmissions = async () => {
  const res = await fetch(`${AM_API_BASE}/my-submissions`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed')
  return (await res.json()).submissions || []
}

// ─── Org Hierarchy + Approval Engine (§3–§8) ───
const JSON_HEADERS = () => ({ ...authHeaders() })

export const getOrgTree = async (department = '') => {
  const q = department ? `?department=${department}` : ''
  const res = await fetch(`${API_BASE}/org-units${q}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch org tree')
  return await res.json()
}

export const createOrgUnit = async (data) => {
  const res = await fetch(`${API_BASE}/org-units`, { method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify(data) })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const updateOrgUnit = async (id, data) => {
  const res = await fetch(`${API_BASE}/org-units/${id}`, { method: 'PATCH', headers: JSON_HEADERS(), body: JSON.stringify(data) })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const deleteOrgUnit = async (id) => {
  const res = await fetch(`${API_BASE}/org-units/${id}`, { method: 'DELETE', headers: JSON_HEADERS() })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const createOrgRole = async (unitId, data) => {
  const res = await fetch(`${API_BASE}/org-units/${unitId}/roles`, { method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify(data) })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const updateOrgRole = async (roleId, data) => {
  const res = await fetch(`${API_BASE}/org-roles/${roleId}`, { method: 'PATCH', headers: JSON_HEADERS(), body: JSON.stringify(data) })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const deleteOrgRole = async (roleId) => {
  const res = await fetch(`${API_BASE}/org-roles/${roleId}`, { method: 'DELETE', headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to delete role')
  return await res.json()
}

export const getAdminUsers = async () => {
  const res = await fetch(`${API_BASE}/admin-users`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch admin users')
  return await res.json()
}

export const getApprovalThresholds = async (filters = {}) => {
  const params = new URLSearchParams()
  if (filters.department) params.append('department', filters.department)
  if (filters.entity_type) params.append('entity_type', filters.entity_type)
  const res = await fetch(`${API_BASE}/approval-thresholds?${params}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch thresholds')
  return await res.json()
}

export const getApprovalThresholdAudit = async () => {
  const res = await fetch(`${API_BASE}/approval-thresholds/audit`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch threshold audit trail')
  return await res.json()
}

export const createApprovalThreshold = async (data) => {
  const res = await fetch(`${API_BASE}/approval-thresholds`, { method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify(data) })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const updateApprovalThreshold = async (id, data) => {
  const res = await fetch(`${API_BASE}/approval-thresholds/${id}`, { method: 'PATCH', headers: JSON_HEADERS(), body: JSON.stringify(data) })
  if (!res.ok) { const e = await res.json(); throw e }
  return await res.json()
}

export const deleteApprovalThreshold = async (id) => {
  const res = await fetch(`${API_BASE}/approval-thresholds/${id}`, { method: 'DELETE', headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to delete threshold')
  return await res.json()
}

export const getMyApprovalQueue = async (filters = {}) => {
  const params = new URLSearchParams()
  if (filters.status) params.append('status', filters.status)
  if (filters.department) params.append('department', filters.department)
  if (filters.entity_type) params.append('entity_type', filters.entity_type)
  const res = await fetch(`${API_BASE}/approvals/my-queue?${params}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch approval queue')
  return await res.json()
}

export const getWorkflowDetail = async (id) => {
  const res = await fetch(`${API_BASE}/approvals/${id}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch workflow')
  return await res.json()
}

export const getWorkflowChainOptions = async (id) => {
  const res = await fetch(`${API_BASE}/approvals/${id}/chain-options`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch chain options')
  return await res.json()
}

export const approvalAction = async (id, action, data = {}) => {
  const res = await fetch(`${API_BASE}/approvals/${id}/${action}`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getSlaDashboard = async (filters = {}) => {
  const params = new URLSearchParams()
  if (filters.department) params.append('department', filters.department)
  if (filters.breached_only) params.append('breached_only', '1')
  const res = await fetch(`${API_BASE}/approvals/sla-dashboard?${params}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch SLA dashboard')
  return await res.json()
}

export const getPendingVendorPayouts = async () => {
  const res = await fetch(`${API_BASE}/finance/vendor-payouts/pending`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch pending payouts')
  return await res.json()
}

export const initiateVendorPayoutApproval = async (paymentId, isException = false) => {
  const res = await fetch(`${API_BASE}/finance/vendor-payouts/${paymentId}/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ is_exception: isException })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getPendingRiderPayoutCycles = async () => {
  const res = await fetch(`${API_BASE}/rider-payout-cycles/pending-approval`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch pending rider payout cycles')
  return await res.json()
}

export const initiateRiderPayoutApproval = async (cycleId, isException = false) => {
  const res = await fetch(`${API_BASE}/rider-payout-cycles/${cycleId}/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ is_exception: isException })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getPendingSponsoredListings = async () => {
  const res = await fetch(`${API_BASE}/account-management/sponsored-listings/pending`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch pending sponsored listings')
  return await res.json()
}

export const initiateSponsoredListingApproval = async (listingId, isException = false) => {
  const res = await fetch(`${API_BASE}/account-management/sponsored-listings/${listingId}/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ is_exception: isException })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getPendingOffers = async () => {
  const res = await fetch(`${API_BASE}/account-management/offers/pending`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch pending offers')
  return await res.json()
}

export const initiateOfferApproval = async (offerId, isException = false) => {
  const res = await fetch(`${API_BASE}/account-management/offers/${offerId}/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ is_exception: isException })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getPendingComplaintRefunds = async () => {
  const res = await fetch(`${API_BASE}/support/complaint-refunds/pending`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch pending complaint refunds')
  return await res.json()
}

export const initiateComplaintRefundApproval = async (complaintId, isException = false) => {
  const res = await fetch(`${API_BASE}/support/complaint-refunds/${complaintId}/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ is_exception: isException })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getPendingInvoices = async () => {
  const res = await fetch(`${API_BASE}/finance/invoices/pending`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch pending invoices')
  return await res.json()
}

export const initiateInvoiceApproval = async (invoiceId, isException = false) => {
  const res = await fetch(`${API_BASE}/finance/invoices/${invoiceId}/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ is_exception: isException })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getPendingRecoveries = async () => {
  const res = await fetch(`${API_BASE}/finance/recoveries/pending`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch pending recoveries')
  return await res.json()
}

export const initiateRecoveryApproval = async (recoveryId, isException = false) => {
  const res = await fetch(`${API_BASE}/finance/recoveries/${recoveryId}/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ is_exception: isException })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getGstFilings = async () => {
  const res = await fetch(`${API_BASE}/finance/gst-filings`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch GST filings')
  return await res.json()
}

export const computeGstLiability = async (year, month) => {
  const res = await fetch(`${API_BASE}/finance/gst-filings/compute`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ year, month })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const initiateGstFilingApproval = async (filingId, isException = false) => {
  const res = await fetch(`${API_BASE}/finance/gst-filings/${filingId}/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ is_exception: isException })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getPendingKycVerifications = async () => {
  const res = await fetch(`${API_BASE}/rider-kyc/pending`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch pending KYC verifications')
  return await res.json()
}

export const initiateKycVerificationApproval = async (riderId, isException = false) => {
  const res = await fetch(`${API_BASE}/rider-kyc/${riderId}/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ is_exception: isException })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getPendingRestaurantAssignments = async () => {
  const res = await fetch(`${API_BASE}/account-management/assignments/pending`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch pending AM assignments')
  return await res.json()
}

export const initiateRestaurantAssignmentApproval = async (restaurantId, accountManagerId) => {
  const res = await fetch(`${API_BASE}/account-management/assignments/initiate-approval`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ restaurant_id: restaurantId, account_manager_id: accountManagerId })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Wave 1: WhatsApp channel + ops ───

export const getCommandCenter = async () => {
  const res = await fetch(`${API_BASE}/command-center`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch command center')
  return await res.json()
}

export const getWaPlacements = async (status = '') => {
  const qs = status ? `?status=${encodeURIComponent(status)}` : ''
  const res = await fetch(`${API_BASE}/wa-placements${qs}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch WhatsApp placements')
  return await res.json()
}

export const rejectWaPlacement = async (id, reason) => {
  const res = await fetch(`${API_BASE}/wa-placements/${id}/reject`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ reason })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const approveWorkflow = async (workflowId) => {
  const res = await fetch(`${API_BASE}/approvals/${workflowId}/approve`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({})
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getWaFloors = async () => {
  const res = await fetch(`${API_BASE}/wa-city-floors`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch city floors')
  return await res.json()
}

export const updateWaFloor = async (cityId, floor_cpc, max_slots) => {
  const res = await fetch(`${API_BASE}/wa-city-floors/${cityId}`, {
    method: 'PUT', headers: JSON_HEADERS(), body: JSON.stringify({ floor_cpc, max_slots })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getWaSessions = async (params = {}) => {
  const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)).toString()
  const res = await fetch(`${API_BASE}/whatsapp/sessions${qs ? `?${qs}` : ''}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch WhatsApp sessions')
  return await res.json()
}

export const assignWaSession = async (waId, assigneeId) => {
  const res = await fetch(`${API_BASE}/whatsapp/sessions/${waId}/assign`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ assignee_id: assigneeId })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getStaff = async (params = {}) => {
  const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)).toString()
  const res = await fetch(`${API_BASE}/staff${qs ? `?${qs}` : ''}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch staff')
  return await res.json()
}

export const createStaff = async (payload) => {
  const res = await fetch(`${API_BASE}/staff`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify(payload)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const deactivateStaff = async (id) => {
  const res = await fetch(`${API_BASE}/staff/${id}/deactivate`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({})
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getWorkforce = async () => {
  const res = await fetch(`${API_BASE}/workforce`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch workforce')
  return await res.json()
}

export const saveWorkforce = async (payload, entryId = null) => {
  const url = entryId ? `${API_BASE}/workforce/${entryId}` : `${API_BASE}/workforce`
  const res = await fetch(url, {
    method: entryId ? 'PATCH' : 'POST', headers: JSON_HEADERS(), body: JSON.stringify(payload)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Ops incidents ───

export const getIncidents = async (params = {}) => {
  const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)).toString()
  const res = await fetch(`${API_BASE}/incidents${qs ? `?${qs}` : ''}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch incidents')
  return await res.json()
}

export const createIncident = async (payload) => {
  const res = await fetch(`${API_BASE}/incidents`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify(payload)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const ackIncident = async (id) => {
  const res = await fetch(`${API_BASE}/incidents/${id}/ack`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({})
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const resolveIncident = async (id, note) => {
  const res = await fetch(`${API_BASE}/incidents/${id}/resolve`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ note })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const assignIncident = async (id, ownerUserId) => {
  const res = await fetch(`${API_BASE}/incidents/${id}/assign`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ owner_user_id: ownerUserId })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── PII audit (auditor) ───

export const getPiiAudit = async (params = {}) => {
  const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)).toString()
  const res = await fetch(`${API_BASE}/pii-audit${qs ? `?${qs}` : ''}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch PII audit')
  return await res.json()
}

// ─── Ops shifts ───

export const getShifts = async (params = {}) => {
  const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)).toString()
  const res = await fetch(`${API_BASE}/shifts${qs ? `?${qs}` : ''}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch shifts')
  return await res.json()
}

export const getShiftCoverage = async (params = {}) => {
  const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)).toString()
  const res = await fetch(`${API_BASE}/shifts/coverage${qs ? `?${qs}` : ''}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch shift coverage')
  return await res.json()
}

export const createShift = async (payload) => {
  const res = await fetch(`${API_BASE}/shifts`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify(payload)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const activateShift = async (id) => {
  const res = await fetch(`${API_BASE}/shifts/${id}/activate`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({})
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const cancelShift = async (id, reason) => {
  const res = await fetch(`${API_BASE}/shifts/${id}/cancel`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ reason })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}


// ─── Safety SOS ───

export const getSos = async (params = {}) => {
  const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)).toString()
  const res = await fetch(`${API_BASE}/sos${qs ? `?${qs}` : ''}`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch SOS alerts')
  return await res.json()
}

export const ackSos = async (id) => {
  const res = await fetch(`${API_BASE}/sos/${id}/ack`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({})
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const resolveSos = async (id, note) => {
  const res = await fetch(`${API_BASE}/sos/${id}/resolve`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify({ note })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}


export const getPiiRetentionRuns = async () => {
  const res = await fetch(`${API_BASE}/pii-audit/retention`, { headers: JSON_HEADERS() })
  if (!res.ok) throw new Error('Failed to fetch retention runs')
  return await res.json()
}


export const rotateShifts = async (payload) => {
  const res = await fetch(`${API_BASE}/shifts/rotate`, {
    method: 'POST', headers: JSON_HEADERS(), body: JSON.stringify(payload)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}
