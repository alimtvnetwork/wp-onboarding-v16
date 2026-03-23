# Memory: style/site-card-visual-feedback
Updated: 2026-03-23

Site card hover uses full solid `--site-card-hover` (HSL 101 62% 46%, #54b435) background — NOT faded/alpha. All text shifts to `--site-card-hover-fg` (near-black) for contrast. Shadow is directional (2px 2px 1px) with minimal blur, angled ~45° — never glow. Dark theme uses light shadow (rgba 255,255,255,0.12), light theme uses dark shadow (rgba 0,0,0,0.2). No borders on hover. Spec: `spec/03-ui-design/01-site-card-hover-contrast.md`.
