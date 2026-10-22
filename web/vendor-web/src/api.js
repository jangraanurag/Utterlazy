import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_URL || 'https://catalog.utterlazy.com/api'

const api = axios.create({ baseURL: API_BASE })

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('vendor_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export const vendorLogin = (email, password) =>
  api.post('/vendor/login', { email, password }).then(r => r.data)

export const getVendorProfile = () => api.get('/vendor/me').then(r => r.data)
export const updateVendorProfile = (data) => api.patch('/vendor/profile', data).then(r => r.data)
export const toggleOnline = () => api.patch('/vendor/toggle-online').then(r => r.data)
export const updateOperatingHours = (hours) => api.patch('/vendor/operating-hours', { operating_hours: hours }).then(r => r.data)

export const getActiveOrders = () => api.get('/vendor/orders/active').then(r => r.data)
export const getAllOrders = (params) => api.get('/vendor/orders', { params }).then(r => r.data)
export const acceptOrder = (id) => api.patch(`/vendor/orders/${id}/accept`).then(r => r.data)
export const rejectOrder = (id, reason) => api.patch(`/vendor/orders/${id}/reject`, { reason }).then(r => r.data)
export const updateOrderStatus = (id, status) => api.patch(`/vendor/orders/${id}/status`, { status }).then(r => r.data)

export const getAnalytics = () => api.get('/vendor/analytics').then(r => r.data)

export const getMenu = () => api.get('/vendor/menu').then(r => r.data)
export const addMenuItem = (data) => api.post('/vendor/menu/items', data).then(r => r.data)
export const editMenuItem = (id, data) => api.patch(`/vendor/menu/items/${id}`, data).then(r => r.data)
export const removeMenuItem = (id) => api.delete(`/vendor/menu/items/${id}`).then(r => r.data)
export const addCategory = (data) => api.post('/vendor/menu/categories', data).then(r => r.data)
export const editCategory = (id, data) => api.patch(`/vendor/menu/categories/${id}`, data).then(r => r.data)
export const removeCategory = (id) => api.delete(`/vendor/menu/categories/${id}`).then(r => r.data)

export const getEarnings = (period = 'today') => api.get('/vendor/earnings', { params: { period } }).then(r => r.data)
export const getStatement = (month, year) => api.get('/vendor/earnings/statement', { params: { month, year } }).then(r => r.data)

const downloadBlob = (url, filename) => {
  const token = localStorage.getItem('vendor_token')
  const headers = { Authorization: `Bearer ${token}` }
  return axios({ url, method: 'GET', responseType: 'blob', headers }).then((res) => {
    const link = document.createElement('a')
    link.href = window.URL.createObjectURL(new Blob([res.data]))
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    setTimeout(() => window.URL.revokeObjectURL(link.href), 10000)
  })
}

// ─── Payouts ───
export const getPayoutCycles = (params) => api.get('/vendor/payouts', { params }).then(r => r.data)
export const getPayoutDetail = (id) => api.get(`/vendor/payouts/${id}`).then(r => r.data)

// ─── Ads (Sponsored Listings) ───
export const getAds = () => api.get('/vendor/ads').then(r => r.data)
export const createAd = (data) => api.post('/vendor/ads', data).then(r => r.data)
export const updateAd = (id, data) => api.patch(`/vendor/ads/${id}`, data).then(r => r.data)
export const deleteAd = (id) => api.delete(`/vendor/ads/${id}`).then(r => r.data)
export const toggleAd = (id) => api.patch(`/vendor/ads/${id}/toggle`).then(r => r.data)
export const getAdPerformance = (id) => api.get(`/vendor/ads/${id}/performance`).then(r => r.data)
export const getAdRecommendations = () => api.get('/vendor/ads/recommendations').then(r => r.data)

// ─── Invoices & Taxes ───
export const getVendorInvoices = (params) => api.get('/vendor/invoices', { params }).then(r => r.data)
export const downloadInvoicePdf = (id, filename) => downloadBlob(`/vendor/invoices/${id}/download`, filename || `Invoice_${id.slice(0,8)}.pdf`)
export const downloadInvoiceBundle = (type, year, month) => api.post('/vendor/invoices/download-bundle', { type, year, month }, { responseType: 'blob' }).then(r => {
  const link = document.createElement('a')
  link.href = window.URL.createObjectURL(new Blob([r.data]))
  link.download = `Invoices_${type}_${year}_${month}.zip`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
})

// ─── GST ───
export const downloadGstr1Report = (year, month) => downloadBlob(`/vendor/gst/gstr1-report?year=${year}&month=${month}`, `GSTR1_${year}_${month}.xlsx`)

export const downloadGstReport = (year, month, filename) => {
  const token = localStorage.getItem('vendor_token')
  return axios({
    url: `${API_BASE}/vendor/gst/monthly/${year}/${month}`,
    method: 'GET',
    responseType: 'blob',
    headers: { Authorization: `Bearer ${token}` },
  }).then((response) => {
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', filename || `GST_Report_${year}_${month}.xlsx`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  })
}

// ─── Complaints ───
export const getComplaints = () => api.get('/vendor/complaints').then(r => r.data)
export const respondComplaint = (id, data) => api.post(`/vendor/complaints/${id}/respond`, data).then(r => r.data)
export const closeComplaint = (id) => api.post(`/vendor/complaints/${id}/close`).then(r => r.data)

// ─── Approvals (AM submissions) ───
export const getPendingApprovals = () => api.get('/account-manager/pending').then(r => r.data)
export const getApprovalHistory = () => api.get('/account-manager/history').then(r => r.data)
export const sendApprovalOtp = (data) => api.post('/account-manager/send-otp', data).then(r => r.data)
export const verifyApprovalOtp = (data) => api.post('/account-manager/verify-otp', data).then(r => r.data)
export const rejectApproval = (data) => api.post('/account-manager/reject', data).then(r => r.data)

export default api

// ─── Wave 1: WhatsApp promotions (paid placements) ───
export const getPublicCities = () => api.get('/cities-public').then(r => r.data)
export const getReliability = () => api.get('/vendor/reliability').then(r => r.data)
export const getWaPlacements = () => api.get('/wa-placements').then(r => r.data)
export const createWaPlacement = (data) => api.post('/wa-placements', data).then(r => r.data)
export const pauseWaPlacement = (id) => api.post(`/wa-placements/${id}/pause`).then(r => r.data)
