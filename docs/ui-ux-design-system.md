# UI/UX Design System

## 1. Visual direction
Premium, restrained enterprise SaaS. Deep slate/navy sidebar, light neutral content, teal primary accent, restrained semantic colors, subtle borders, minimal decoration. No default Laravel admin look, no heavy gradients, no glassmorphism, no marketing-size headings, few animations.

## 2. Design tokens (CSS variables in `resources/css/app.css`, mapped in Tailwind)
| Token | Light value |
|---|---|
| `--background` | #F6F8FA |
| `--surface` (cards) | #FFFFFF |
| `--border` | #E3E8EE |
| `--foreground` | #0F172A |
| `--muted-foreground` | #64748B |
| `--sidebar` | #0B1B2E (navy) |
| `--sidebar-foreground` | #CBD5E1 |
| `--primary` | #0F9488 (teal), hover #0D7C72 |
| `--severity-high` | #DC2626 on #FEE2E2 |
| `--severity-medium` | #B45309 on #FEF3C7 |
| `--severity-low` | #475569 on #E2E8F0 |
| `--success` | #15803D on #DCFCE7 |
| `--info` | #0369A1 on #E0F2FE |

Contrast target WCAG AA (4.5:1 for text); verify teal-on-white for text use during S01 and darken if needed. Dark mode is not required in Stage 1.
Chart palette: teal #0F9488, slate #334155, amber #D97706, red #DC2626, light teal #99D6CF.

## 3. Typography, spacing, shape
Font: Inter, self-hosted via npm (`@fontsource-variable/inter`), no CDN. Scale: page title 24/32 semibold, section 18/28 semibold, body 14/20, caption 12/16, KPI value 28/36 semibold tabular-nums. Spacing on a 4px grid. Radius 8px cards/inputs, 6px badges, full pills for status chips. Border 1px `--border`; only `shadow-sm` on cards.

## 4. Application shell
Left sidebar 248px (icon rail at ≤1280px, off-canvas sheet at ≤768px), top bar with page title, breadcrumbs, notification bell (unread count, popover list), user menu. Content max width 1280px, padding 24px (16px mobile). Primary navigation (Lucide icons): Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes, Settings.
Role visibility: Owner sees all; Manager sees all except company-level settings sections; Staff sees Today (own tasks) and Tasks only.

## 5. Reusable components (build only when reused at least twice)
shadcn-vue: Button, Input, Textarea, Select, Checkbox, Label, Dialog, Sheet (drawer), Tabs, Table, Badge, Card, Skeleton, Sonner toasts, DropdownMenu, Popover, Tooltip, Calendar/DatePicker, Progress, Alert.
Custom: `AppShell`, `PageHeader`, `KpiCard`, `SeverityBadge`, `StatusBadge`, `EmptyState`, `ErrorState`, `InsufficientDataNotice`, `EChart` (tree-shaken ECharts wrapper with resize), `FileDropzone`, `EvidenceList`, `Timeline`, `AudioPlayer`.

## 6. Screens
| Screen | Structure |
|---|---|
| Login | Centered card, product mark, email/password, error text. |
| Today (dashboard) | KPI row (revenue, change, target achievement, active issues, high priority, open tasks, overdue, resolved), freshness banner, revenue vs target chart, weekly change chart, latest brief card, priority actions list. |
| Data Imports | History table (status, file, range, counts, uploader). Upload: dropzone, then validation preview (counts, error table, coverage gaps), then confirm. Import detail: errors, source file info. |
| Issues | Filterable table (severity, status). Detail: badges, evidence panel, timeline, linked meetings and tasks, outcomes, feedback, status actions. |
| Briefs | History list; detail with finding cards (authoritative figures), source links, AI/fallback label, voice player, generate/retry. |
| Meetings | List; create dialog; detail tabs: Overview, Transcript (editable), Decisions, Task drafts (review cards with inline edit, approve/reject). |
| Tasks | Table with filters (status, assignee, overdue); My Tasks default for Staff; detail page with description, links, comments, status control. |
| Outcomes | List of issues with outcomes; comparison view (baseline vs follow-up values, delta, chart), notes, useful/not useful. |
| Settings | Tabs: Company, Staff, Sales department (branches, trading days), Rules, Import template (CSV template download and column spec). |

## 7. Responsive behavior
Tailwind default breakpoints. Tables become stacked cards below 768px for Tasks and Issues. KPI grid 4→2→1 columns. Charts keep a 240px minimum height. Forms are single column on mobile. Touch targets at least 40px.

## 8. Accessibility
Semantic landmarks, visible focus ring, keyboard-navigable dialogs, `aria-live` for toasts and job status, severity shown by icon plus label (never color alone), text/table alternative for each chart, labels on all inputs, `prefers-reduced-motion` respected.

## 9. Loading, empty, error, insufficient-data states
- Loading: skeletons matching layout; job progress by polling every 3 seconds with a status badge.
- Empty: icon, one-line explanation, primary action (for example "Upload sales data").
- Error: inline `ErrorState` with retry; Inertia error pages for 403/404/419/500/503.
- Insufficient data: neutral info style (never red) stating which data is missing.
- Provider failure: Alert naming the failed capability and the recovery action.

## 10. Toasts and notifications
Toasts for transient confirmations (3–5 seconds). Persistent in-app notifications in the bell for urgent issues and task assignment, with mark-as-read and a link to the subject.

## Application shell (sidebar)

One shared shell (`AppSidebarShell.vue`) serves the company app and the platform panel. Desktop (769px and up): a dark sidebar that is 248px wide and collapses to a 64px icon rail with tooltips; the toggle is in the header and `Ctrl/Cmd + B` also toggles it (ignored while typing). The choice is stored in the browser (`bp.sidebar.collapsed`); on a first visit it starts collapsed below 1281px and expanded above. Phones (768px and below): the sidebar is a slide-out menu. The signed-in user (avatar initials, name, and role and department or email) is at the bottom left with a Sign out menu. The width change is a 200ms transition.

## Visual language v2 (rules for every page)

Goal: a business owner understands the page in five seconds. Presentation only: never change props, routes, permissions or behavior while restyling.

1. **Numbers first.** Put the headline figures at the top as `MetricTile`s (4 across on wide screens, 2 on tablets, 1 on phones): label, one big number, one short caption, a tinted icon, tone for good, warning or bad.
2. **One short line per section.** Use `SectionHeader` for every section title (optional icon tile, optional one-line description of at most about 90 characters, actions in its slot). Long disclaimers become small footnotes at the bottom of the card or are cut. No paragraph under a heading.
3. **Detail pages are two columns on wide screens**: the evidence and the work on the left, and a 360px side column on the right with the primary actions, key facts, feedback and history (`grid xl:grid-cols-[minmax(0,1fr)_360px]`). Single column below xl, actions still reachable.
4. **Lists** are rows inside a `Card class="gap-0 p-0"` with `divide-y`: a leading icon or initials tile, the main text in `font-medium`, secondary text in `text-xs text-muted-foreground`, then status badges and a chevron. Rows are links with a hover tint. Use the shared `Table` (quiet uppercase header band, 14px cells) for true tables of comparable columns.
5. **Cards**: `Card` with `p-5`, soft border and shadow (already global). A coloured left edge (`border-l-4`) marks severity or result: red for high or breached, amber for medium, green for good, slate for neutral.
6. **Timelines** (`TimelineList`, `TraceTimeline`) for history. Never a bare list of dates.
7. **Empty states** use `EmptyState` with a relevant icon and one friendly sentence, plus the next action when there is one.
8. **Spacing**: sections `space-y-6`, grids `gap-4`, page header `mb-6`. Text sizes: page title 24px, section title 16px, body 14px, secondary 12px. Numbers use `tabular-nums`.
9. **Colour**: teal is the brand and the only accent; severity colours only for real severity. Status always has text or an icon, never colour alone (accessibility rule is unchanged).
10. **Copy**: plain, short, friendly. Say what the number means, not how it was computed.
