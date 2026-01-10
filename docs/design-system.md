# Valid Email Verifier - Design System

This document defines the shared visual system for Marketing, Customer Portal, and Admin UI.
Use these tokens and typography scales to keep the product consistent.

## Brand Colors

Primary
- Primary: #1E7CCF
- Primary Hover: #1866AD
- Primary Active: #14578F
- Primary Soft (Background): #E9F2FB

Neutrals (UI)
- Page Background: #F8FAFC
- Card / Surface: #FFFFFF
- Alternate Section: #F1F5F9
- Border Light: #E2E8F0
- Border Medium: #CBD5E1

Text
- Heading Text: #0F172A
- Body Text: #334155
- Muted Text: #64748B
- Placeholder / Disabled: #94A3B8

Status
- Success: #16A34A
- Success Light: #DCFCE7
- Warning: #F59E0B
- Warning Light: #FEF3C7
- Error: #DC2626
- Error Light: #FEE2E2
- Info: #0EA5E9
- Info Light: #E0F2FE

Buttons
- Primary Button: #1E7CCF (text #FFFFFF)
- Primary Button Hover: #1866AD
- Secondary Button: #FFFFFF (border/text #1E7CCF)
- Secondary Hover Background: #E9F2FB
- Danger Button: #DC2626 (text #FFFFFF)
- Danger Hover: #B91C1C

Forms
- Input Background: #FFFFFF
- Input Border: #CBD5E1
- Focus Border: #1E7CCF
- Focus Shadow: rgba(30, 124, 207, 0.3)
- Disabled Background: #F1F5F9

Optional Accents
- Purple Accent: #7C3AED
- Teal Accent: #14B8A6
- Orange Accent: #FB923C

## CSS Variables

:root {
  --primary: #1E7CCF;
  --primary-hover: #1866AD;
  --primary-light: #E9F2FB;

  --bg-main: #F8FAFC;
  --bg-card: #FFFFFF;

  --text-heading: #0F172A;
  --text-body: #334155;
  --text-muted: #64748B;

  --border-light: #E2E8F0;

  --success: #16A34A;
  --warning: #F59E0B;
  --error: #DC2626;
  --info: #0EA5E9;
}

## Tailwind Tokens

The palette is wired into `tailwind.config.js` as theme tokens:

Brand
- `brand` / `brand-hover` / `brand-active` / `brand-soft`

Surfaces
- `surface-page` (page background)
- `surface` (card/surface)
- `surface-alt` (alternate section)

Borders
- `border-light` / `border-medium`

Text (ink)
- `ink-heading` / `ink-body` / `ink-muted` / `ink-disabled`

Status
- `status-success` / `status-success-light`
- `status-warning` / `status-warning-light`
- `status-error` / `status-error-light`
- `status-info` / `status-info-light`

Accents
- `accent-purple` / `accent-teal` / `accent-orange`

Examples
- `bg-brand`, `bg-brand-soft`, `text-ink-heading`, `border-border-light`
- `bg-status-success-light`, `text-status-error`

## Typography

Font Family
- Primary: Figtree, system-ui, -apple-system, Segoe UI, sans-serif

Base Settings
- Base font size: 16px
- Body line height: 1.5 (use `leading-relaxed` for long text)
- Heading line height: 1.2 (use `leading-tight`)

Scale (Desktop / Default)
- text-xs: 12px
- text-sm: 14px
- text-base: 16px
- text-lg: 18px
- text-xl: 20px
- text-2xl: 24px
- text-3xl: 30px
- text-4xl: 36px

Usage Guidelines
- Page title: text-2xl to text-3xl, font-semibold
- Section title: text-xl, font-semibold
- Card title: text-base to text-lg, font-semibold
- Body: text-base
- Meta / labels: text-sm or text-xs with text-muted

Suggested Settings
- Font weights: 400 (body), 500 (labels), 600 (headings), 700 (hero only)
- Letter spacing: use `tracking-wide` for uppercase labels
- Line length: keep paragraphs around 60-80 characters where possible

## Notes

- Use these tokens across marketing, customer portal, and admin to keep the UI consistent.
- Avoid hardcoded colors in components; prefer CSS variables or Tailwind theme tokens.
