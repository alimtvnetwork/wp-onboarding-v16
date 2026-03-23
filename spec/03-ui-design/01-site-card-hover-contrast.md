# Site Card Hover & Color Contrast Spec

> **Created:** 2026-03-23  
> **Status:** ✅ Active

---

## Hover Background Color

- **Exact color:** `#54b435` → HSL `101 62% 46%`
- On hover the card background MUST be **full solid** `#54b435`, not a faded/alpha variant
- This is defined as the CSS variable `--site-card-hover` in `index.css`

## Text Contrast on Hover

- When the card is hovered (green background), **all text must turn dark/black** for readability
- Use `--site-card-hover-fg: 0 0% 10%` (near-black)
- Muted text, icons, badges — all shift to dark on hover
- This is the **contrast game**: bright background → dark text

## Shadow Rules (NOT Glow)

- **Never use glow** (large spread, bright color, high blur)
- Use a **directional shadow** angled ~45° with minimal blur (≤1px)
- Dark theme: white/light shadow → `2px 2px 1px rgba(255,255,255,0.12)`
- Light theme: dark shadow → `2px 2px 1px rgba(0,0,0,0.2)`
- Shadow is subtle, adds depth — NOT a halo/glow effect

## Border on Hover

- **No border** on hover — set to `transparent`
- The solid background color is the visual indicator, not a border

## Accent Elements on Hover

- Globe icon container: transitions to `--site-card-hover` background
- Bottom divider: transitions to `--site-card-hover` at reduced opacity
- Status badges: maintain their semantic colors but text darkens

## Design Token Reference

```css
:root {
  --site-card-hover: 101 62% 46%;       /* #54b435 */
  --site-card-hover-fg: 0 0% 10%;       /* near-black for contrast */
  --site-card-hover-shadow: 2px 2px 1px rgba(0,0,0,0.2);
}
.dark {
  --site-card-hover-shadow: 2px 2px 1px rgba(255,255,255,0.12);
}
```

## Anti-Patterns (NEVER DO)

1. ❌ `hover:bg-[#54b435]/10` — faded, looks washed out
2. ❌ `hover:shadow-[0_0_20px_...]` — that's a glow, not a shadow
3. ❌ Large blur values (>2px) on hover shadows
4. ❌ Bright text on bright background (no contrast)
5. ❌ Glow borders (`ring`, `outline` with spread)
