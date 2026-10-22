# 🎨 Khaalo / Welo — Design System

## Brand
| Element | Value |
|---------|-------|
| Name | Khaalo (also "Welo" internally) |
| Tagline | Tier 2 & Tier 3 India ka apna food delivery platform |
| Tone | Premium, editorial, welcoming, confident |
| Voice | Hindi + Hinglish + English mix |

## Colors

### Primary Palette
| Token | Color | Hex | Usage |
|-------|-------|-----|-------|
| Primary | Orange | `#FF6B00` | CTAs, highlights, brand |
| Primary Dark | Deep Orange | `#E85D00` | Hover states |
| Secondary | Teal | `#007B8A` | Accents, badges |
| Tertiary | Gold | `#B08D57` | Premium indicators |

### Light Theme
| Token | Hex | Usage |
|-------|-----|-------|
| Background | `#FDFAF6` | Page background |
| Surface | `#FFFFFF` | Cards, modals |
| Surface Variant | `#F4F1ED` | Elevated surfaces |
| On Surface | `#1A1A1A` | Primary text |
| On Surface Variant | `rgba(26,26,26,0.6)` | Secondary text |
| Outline | `rgba(0,0,0,0.08)` | Borders |
| Outline Variant | `rgba(0,0,0,0.04)` | Subtle borders |

### Dark Theme
| Token | Hex | Usage |
|-------|-----|-------|
| Background | `#0A0E1A` | Page background |
| Surface | `#141A2B` | Cards, modals |
| Surface Variant | `#1E2840` | Elevated surfaces |
| On Surface | `#E8E6E1` | Primary text |
| On Surface Variant | `rgba(232,230,225,0.6)` | Secondary text |
| Primary | `#FF8C42` | CTA, highlights (dark mode optimized) |

### Semantic Colors
| Token | Hex | Usage |
|-------|-----|-------|
| Success | `#22C55E` | Delivered, active |
| Error | `#EF4444` | Cancelled, errors |
| Warning | `#F59E0B` | Pending, warnings |

## Typography

### Font Stack
| Role | Font | Fallback |
|------|------|----------|
| Display/Headings | Space Grotesk | sans-serif |
| Body | Inter | -apple-system, sans-serif |
| Decorative | Playfair Display | serif |

### Size Scale
| Level | Size | Weight | Font |
|-------|------|--------|------|
| Display XL | 4.5rem | 700 | Space Grotesk |
| Display LG | 3rem | 700 | Space Grotesk |
| Display MD | 2rem | 700 | Space Grotesk |
| Title | 1.5rem | 700 | Space Grotesk |
| Body LG | 1rem | 400 | Inter |
| Body MD | 0.875rem | 400 | Inter |
| Body SM | 0.75rem | 500 | Inter |
| Label | 0.625rem | 800 | Space Grotesk |
| Badge | 0.625rem | 800 | Space Grotesk |

## Shapes & Radius
| Level | Value | Usage |
|-------|-------|-------|
| Extra Small | 10px | Badges, small buttons |
| Small | 12px | Inputs, form elements |
| Medium | 20px | Cards, modals |
| Large | 32px | Large cards, containers |
| Extra Large | 48px | Hero sections |
| Full | 9999px | Pills, avatars |

## Shadows
| Level | Value |
|-------|-------|
| Soft | `0 10px 40px -10px rgba(0,0,0,0.08)` |
| Glow | `0 0 30px rgba(255,107,0,0.2)` |
| Glass | `inset 0 0 0 1px rgba(255,255,255,0.06)` |

## Component Design Principles

### Cards
- Border: 1px solid `var(--glass-edge)`
- Border radius: `var(--radius-lg)` (20px)
- Background: `var(--color-surface)`
- Hover: translateY(-8px), elevated shadow

### Buttons
- Border radius: 16px
- Font: Space Grotesk, 700 weight, uppercase, 1.5px letter spacing
- Primary: Orange gradient with glow shadow
- Secondary: Elevated surface with border

### Navigation (Mobile)
- Bottom tab bar with active indicator
- Material Symbols outline icons
- Smooth transitions between tabs

### KDS (Kitchen Display)
- Dark background (`#0A0E1A`)
- Card grid: `repeat(auto-fill, minmax(380px, 1fr))`
- Timer color-coded urgency: green → yellow → red
- Sound alerts for new orders

### Admin Dashboard (War Room)
- Full-screen map view with Leaflet
- Color-coded markers: Orange (restaurant), Blue (rider), Green (customer)
- Animated rider markers (pulsing)
- Dashed lines between restaurant → customer

## Icons
- Google Material Symbols (Outlined style)
- 24px default size
- Font variation settings for filled/outlined states

## Spacing
- Grid: 8px base unit
- Page padding: 16px (mobile), 32px (tablet), 48px+ (desktop)
- Card padding: 24px internal

## Responsive Breakpoints
| Breakpoint | Width |
|-----------|-------|
| Mobile | < 640px |
| Tablet | 640px - 1024px |
| Desktop | > 1024px |

## Platform-Specific Notes

### Flutter (Mobile Primary)
- Shared component library in `packages/ui`
- Riverpod for state management
- Dio for API calls
- Shared theme via `WeloTheme` class
- Dark mode supported via `ThemeMode` provider

### Web (Secondary)
- Vue 3 with Composition API (`<script setup>`)
- Tailwind CSS 4 with custom theme tokens
- TypeScript enabled on admin-web
- CSS variables for dynamic theming
- Service worker for PWA offline support
