# 📋 Khaalo / Welo — Product Requirements Document

## 1. Product Overview
A food delivery aggregator platform for tier 2 and tier 3 cities of India. Connects customers with local restaurants via delivery partners. Pure aggregator model — no restaurant ownership.

## 2. Target Users

### Primary: Customers
- Age: 16-45
- Income: ₹15,000-₹60,000/month
- Tech comfort: Mobile-first, app-savvy
- Language: Hindi, Hinglish, English (regional languages future)
- Pain points: Limited restaurant discovery, no local options, high delivery fees

### Secondary: Restaurants (Registered Partners)
- Size: Small to medium local restaurants
- Tech comfort: Basic smartphone usage
- Needs: Easy onboarding, simple order management, affordable commission

### Tertiary: Riders (Delivery Partners)
- Vehicle: Bicycle, scooter, bike, EV
- Needs: Fair earnings, safety, navigation

## 3. Key Differentiators (vs Swiggy/Zomato)
| Feature | Swiggy/Zomato | Khaalo |
|---------|---------------|--------|
| Tier 2/3 focus | Weak | ✅ Primary focus |
| Voice ordering | No | ✅ Hindi/English NLP |
| WhatsApp ordering | No | ✅ (P1) |
| Offline PWA | No | ✅ Service worker |
| Kitchen Display System | Paid addon | ✅ Free for partners |
| AI Menu Photo → Digital | No | ✅ Gemini Vision |
| Regional festivals | Generic | ✅ Local campaigns |
| Sachet pricing | No | ✅ ₹10-200 delivery passes |
| UPI Instant Payout | Delayed | ✅ Rider instant payout |
| Advanced inventory | No | ✅ Opt-in, recipe-based |

## 4. Core Features (Built)
See `build_status.md` for complete list (~70+ features).

## 5. Business Model
| Revenue Stream | Details |
|---------------|---------|
| Commission per order | % of order value per restaurant |
| Delivery fee | Split between customer/platform/restaurant |
| Sponsored listings | Restaurants pay for visibility |
| Membership | Subscription (disabled, admin-toggleable) |
| Ads/Banners | Promotional placements |

## 6. Success Metrics
- Orders per day per city
- Rider acceptance rate > 90%
- Average delivery time < 35 min
- Restaurant onboarding time < 24 hours
- Customer retention rate > 40%

## 7. Constraints & Rules
- No restaurant ownership (pure aggregator)
- Razorpay only for online payments + COD
- No BNPL, Postpaid, or Multi-wallet
- SMS OTP backend ready but app-based OTP only
- Postpaid/Khata system not needed
- Membership: built but disabled, admin can enable
- Advanced inventory: optional opt-in, simple on/off always available
- **Money-moving approvals (payouts, invoices, recoveries, GST filings, refunds, KYC, AM assignments) go through the Org Hierarchy Approval Engine** — threshold-based escalation, no super_admin bypass, full audit trail

## 8. Future Roadmap (Post-Launch)
- Multi-language UI activation
- Membership activation
- Full offline mode (Flutter)
- Rider navigation polish
- TypeScript migration complete
- E2E test expansion
