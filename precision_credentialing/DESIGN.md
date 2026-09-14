---
name: Precision Credentialing
colors:
  surface: '#ffffff'
  surface-dim: '#e2e8f0'
  surface-bright: '#ffffff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f8fafc'
  surface-container: '#f1f5f9'
  surface-container-high: '#e2e8f0'
  surface-container-highest: '#cbd5e1'
  on-surface: '#000000'
  on-surface-variant: '#475569'
  inverse-surface: '#0f172a'
  inverse-on-surface: '#ffffff'
  outline: '#94a3b8'
  outline-variant: '#e2e8f0'
  surface-tint: '#21a249'
  primary: '#21a249'
  on-primary: '#ffffff'
  primary-container: '#1b873d'
  on-primary-container: '#ffffff'
  inverse-primary: '#86e3a2'
  secondary: '#5e9fd4'
  on-secondary: '#ffffff'
  secondary-container: '#eaf2f9'
  on-secondary-container: '#1b4467'
  tertiary: '#21a249'
  on-tertiary: '#ffffff'
  tertiary-container: '#e8f6ed'
  on-tertiary-container: '#0a3917'
  error: '#dc2626'
  on-error: '#ffffff'
  error-container: '#fee2e2'
  on-error-container: '#7f1d1d'
  primary-fixed: '#e8f6ed'
  primary-fixed-dim: '#c2ebd0'
  on-primary-fixed: '#0a3917'
  on-primary-fixed-variant: '#15632d'
  secondary-fixed: '#dceaf6'
  secondary-fixed-dim: '#b7d6ed'
  on-secondary-fixed: '#0e2c45'
  on-secondary-fixed-variant: '#2b5f87'
  tertiary-fixed: '#e8f6ed'
  tertiary-fixed-dim: '#c2ebd0'
  on-tertiary-fixed: '#0a3917'
  on-tertiary-fixed-variant: '#15632d'
  background: '#f8fafc'
  on-background: '#000000'
  surface-variant: '#dae2fd'
typography:
  display:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '600'
    lineHeight: 44px
    letterSpacing: -0.025em
  display-mobile:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
    letterSpacing: -0.005em
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
    letterSpacing: 0em
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
    letterSpacing: 0em
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.01em
  caption:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '500'
    lineHeight: 14px
    letterSpacing: 0.02em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  space-2xs: 0.25rem
  space-xs: 0.5rem
  space-sm: 0.75rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
  space-2xl: 3rem
  gutter-mobile: 1rem
  gutter-desktop: 1.5rem
  max-content-width: 1280px
---

## Brand & Style
The design system embodies modern enterprise utility, architectural clarity, and institutional trust. Tailored for high-stakes certification distribution, recipient verification, and issuance workflows, it removes decorative clutter in favor of scannable data layouts, deliberate negative space, and crisp micro-interactions.

The visual style is **Refined Modern SaaS**:
- Surfaces prioritize pure whites and subtle cool slates over layered skeuomorphism.
- Focus rests strictly on verification statuses, delivery metrics, and credential previews.
- Interactions are crisp and understated, utilizing 150ms transitions and structural outline treatments instead of heavy motion or dramatic depth.
- The tone conveys institutional authority, precision engineering, and frictionless usability.

## Colors
The color architecture relies on high-contrast legibility between deep slate typography and cool background tones, punctuated by an intentional royal blue for active workflows.

### Primary & Interaction
- **Primary Accent (`#2563EB`)**: Interactive primary buttons, focused form rings, key progress indicators, and confirmed states.
- **Primary Hover / Deep (`#1D4ED8`)**: Depressed states and hover interactions.
- **Primary Subtle (`#EFF6FF`)**: Active navigational items, highlight pills, and table row selections.

### Neutrals & Structure
- **Canvas (`#F8FAFC`)**: The overall page backdrop, providing soft visual separation from white components.
- **Subtle Surface (`#F1F5F9`)**: Table headers, nested metric containers, and disabled states.
- **Surface / Card (`#FFFFFF`)**: Component canvases, modals, popovers, and elevated content blocks.
- **Border / Divider (`#E2E8F0`)**: 1px structural definition on all card surfaces, inputs, and tab bars.
- **Text Primary (`#0F172A`)**: Headings, dominant table values, and active interface labels.
- **Text Secondary (`#64748B`)**: Secondary metadata, timestamps, helper text, and column descriptors.
- **Text Muted (`#94A3B8`)**: Placeholders, breadcrumb separators, and disabled text.

### Status Tokens
- **Success (`#10B981`)**: Verified credentials, active distributions, and completed batches. Paired with surface `#ECFDF5`, border `#A7F3D0`, and text `#065F46`.
- **Warning (`#F59E0B`)**: Pending approvals, expiries within 30 days. Paired with surface `#FFFBEB`, border `#FDE68A`, and text `#92400E`.
- **Destructive / Error (`#EF4444`)**: Revoked credentials, delivery failures, unrecoverable actions. Paired with surface `#FEF2F2`, border `#FECACA`, and text `#991B1B`.

## Typography
Typographic hierarchy is governed by tight letter-spacing and rigorous vertical cadence. 

- **Display & Headings**: Rendered in semibold (`600`) with negative tracking to prevent loose whitespace across large headers and KPI tiles.
- **Body Content**: Employs normal weight (`400`) at 14px for general SaaS utility tables, density-critical logs, and batch lists. Paragraph copy leverages 16px.
- **Labels & Microcopy**: Uses medium (`500`) with neutral to slightly positive tracking for tabular figures, status indicators, and column headers. Tabular numerals (`font-variant-numeric: tabular-nums`) are mandatory across all metric counters, dates, and identifier codes.

## Layout & Spacing
The layout adheres to a fixed-max-width model with a strict 4px/8px geometric scale:

- **Grid Architecture**: 12-column responsive fluid grid bounded at `1280px` maximum viewport content width. Outer margins are `16px` on mobile (`<768px`), `24px` on tablet (`768px–1024px`), and `32px` on desktop (`>1024px`).
- **Dashboard Shell**: Dual-tier navigation consisting of a fixed `240px` collapsable sidebar navigation and a persistent `64px` utility header. Main content regions use consistent `32px` (`space-xl`) vertical section gaps.
- **Density Control**: Metric tables and issuance logs switch to compact vertical padding (`8px` top/bottom per row) to accommodate high-volume batch inspection, while credential preview workspaces utilize generous padding (`24px` to `32px`).

## Elevation & Depth
Depth in this system avoids high-contrast shadows or murky drop-offs. Visual separation relies primarily on a 1px border rule paired with subtle ambient diffusion.

- **Level 0 (Flat Canvas)**: Background at `#F8FAFC`. Unbordered, unraised.
- **Level 1 (Cards, Modules, Tables)**: Pure white `#FFFFFF` surface with a continuous 1px `#E2E8F0` border and `box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.03), 0 1px 2px -1px rgba(15, 23, 42, 0.03)`.
- **Level 2 (Hover States, Flyouts, Dropdowns)**: Surface `#FFFFFF`, border `#E2E8F0`, `box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.05), 0 2px 4px -2px rgba(15, 23, 42, 0.05)`.
- **Level 3 (Modals, Slide-over Panels)**: Surface `#FFFFFF`, border `#CBD5E1`, `box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.04)`.
- **Overlays**: System modals use a backdrop of `#0F172A` at `40%` opacity with a 2px backdrop blur (`backdrop-filter: blur(2px)`).

## Shapes
The structural geometry balances approachable modern SaaS interfaces with enterprise rigor:

- **Cards & Data Panels**: Fixed `12px` border radius (`rounded-xl` equivalent in this configuration) with clipped overflows.
- **Inputs & Buttons**: Scaled at `8px` to `10px` (`rounded-md` to `rounded-lg`) to preserve crisp form edges.
- **Badges, Status Tags, & Avatars**: Set to full pill (`9999px`) to create clear geometric contrast against rectangular cards and input fields.
- **Modal Containers**: `14px` border radius to anchor floating elements softly against backdrop dims.

## Components

### Buttons
- **Primary**: Background `#2563EB`, text `#FFFFFF`, radius `8px`, height `38px`, font `label-md`. Hover state shifts to `#1D4ED8`. Focus adds `0 0 0 3px rgba(37, 99, 235, 0.2)`.
- **Secondary / Outline**: Background `#FFFFFF`, border 1px `#E2E8F0`, text `#0F172A`. Hover transitions background to `#F8FAFC` and border to `#CBD5E1`.
- **Ghost**: Background transparent, text `#64748B`. Hover state uses `#F1F5F9` and text `#0F172A`.
- **Destructive**: Background `#FEF2F2`, border 1px `#FECACA`, text `#991B1B`. Hover state shifts to `#EF4444` with text `#FFFFFF`.

### Inputs & Selectors
- Text fields have a fixed height of `40px`, background `#FFFFFF`, border 1px `#E2E8F0`, and radius `8px`.
- Padding is `8px 12px`. Helper and validation labels sit at `12px` font size with `4px` top margin.
- Focus state explicitly outlines with `#2563EB` and an outer halo of `0 0 0 3px rgba(37, 99, 235, 0.15)`. No default browser outlines.

### Chips & Status Badges
- Pill-shaped badges (`rounded-full`) with a height of `24px` and padding `2px 10px`.
- Status layouts use a 3-part token set (Surface / Border / Text):
  - **Success (Issued / Valid)**: `#ECFDF5` / `#A7F3D0` / `#065F46`. Includes a 6px solid `#10B981` leading dot indicator.
  - **Warning (Expiring / Pending)**: `#FFFBEB` / `#FDE68A` / `#92400E`.
  - **Error (Revoked / Failed)**: `#FEF2F2` / `#FECACA` / `#991B1B`.
  - **Neutral (Draft / Queued)**: `#F1F5F9` / `#E2E8F0` / `#475569`.

### Cards & Tables
- **Cards**: Pure white `#FFFFFF`, 1px `#E2E8F0` solid border, `12px` radius. Interior padding is strictly `20px` or `24px`.
- **Tables**: Borderless interior cell divisions using `1px solid #F1F5F9`. Header rows use `#F8FAFC` background with uppercase `11px` (`caption`) tracking in `#64748B`. Row hover invokes `#F8FAFC` smoothly across the entire row span.

### Checkboxes & Radios
- Size `16px x 16px`, border 1px `#CBD5E1`, radius `4px` (checkbox) or full circle (radio).
- Checked state fills `#2563EB` with white iconography.

### Credential Preview Container
- A specialized viewport card bounded by `#E2E8F0` with a subtle check pattern canvas background (`#F8FAFC`) to frame high-resolution PDF and SVG certificate renders with absolute structural fidelity.