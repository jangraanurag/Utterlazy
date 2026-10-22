# UtterLazy Rider App

Flutter delivery partner app for UtterLazy food delivery platform.

## Screens (18+)

| Tab | Screens |
|-----|---------|
| **Dashboard** | Metrics (completions/earnings/distance), time-slot bar chart, top restaurants, period comparison (week/month) |
| **History** | Order list with pay breakdown, IST status timeline, paid/unpaid badge |
| **Live** | Map, online/offline toggle, assignment alert, pickup/delivery status, OTP confirmation, navigation, SOS |
| **Payout** | Daily payout cycle list, per-order detail, paid/unpaid status |
| **Profile** | Partner ID, KYC status, vehicle info, settings, Help & Support |
| **KYC** | Document upload (Aadhaar/DL/PAN/selfie/RC/insurance), camera/gallery, expiry tracking |
| **Other** | Login, Earnings, Training (3 modules), Documents, Permission Status |

## Tech

- Flutter + Riverpod state management
- Shared `packages/api`, `packages/core`, `packages/ui`
- Ola Maps for navigation
- FCM for push notifications

## Build

```bash
cd mobile/apps/rider_app
flutter build apk --debug
```
