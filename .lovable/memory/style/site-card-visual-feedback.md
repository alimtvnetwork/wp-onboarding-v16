# Memory: style/site-card-visual-feedback
Updated: 2026-03-23

Site card hover uses full solid `--site-card-hover` (HSL 101 62% 46%, #54b435) background — NOT faded/alpha. All text, badges, icons, status indicators, and action buttons shift to `--site-card-hover-fg` (near-black) for contrast via `group-hover:text-site-card-hover-foreground`. This includes CategoryBadge, plugin tags, status badges, connection icons, backup indicators, and the Retest/Test button. Shadow is directional (2px 2px 1px) with minimal blur — never glow. Dark theme uses light shadow (rgba 255,255,255,0.12), light theme uses dark shadow (rgba 0,0,0,0.2). No borders on hover. Cards use `grid-cols-[repeat(auto-fill,minmax(340px,1fr))]` for min-width with wrap. Spec: `spec/03-ui-design/01-site-card-hover-contrast.md`.
