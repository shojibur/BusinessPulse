---
inclusion: fileMatch
fileMatchPattern: 'resources/**/*.{vue,ts,css}'
---
# UI/UX

Follow `docs/ui-ux-design-system.md`.
- Navy sidebar, light neutral surface, teal primary, restrained red/amber/green. Inter font, 4px grid, 8px radius, subtle borders.
- Use shadcn-vue components and the defined tokens; no hardcoded hex values in components.
- Every data view needs loading (skeleton), empty, error, and insufficient-data states. Insufficient data is neutral, never red.
- Severity uses icon + label + color. Charts have text alternatives.
- Responsive from 360px. No oversized headings, gradients, glassmorphism, or decorative animation.
- Frontend never computes authoritative metrics; it displays server values.
