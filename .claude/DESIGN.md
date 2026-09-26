# AtomPay Mobile — Design

The app must feel like the website (`resources/css/app.css`): warm paper background,
near-black "nucleus" surfaces for money, a five-colour **spectrum** accent from the atom logo,
a grotesque display face, and monospace eyebrow labels.

## 1. Tokens

### Colour

| Token | Hex | Use |
|---|---|---|
| `nucleus` | `#050708` | Primary buttons, dark cards (limit card, calculator), logo disc |
| `paper` | `#FBFAF7` | App background (light) |
| `surface` | `#FFFFFF` | Cards on paper |
| `ink` | `#14151A` | Body text |
| `muted` | `#6C6C74` | Secondary text, eyebrows, hints |
| `line` | `rgba(20,21,26,0.10)` → `#1A14151A` | Borders, dividers |
| `line2` | `rgba(20,21,26,0.06)` → `#0F14151A` | Table row dividers |
| `amber` | `#FAA53A` | Due soon, highlights, selection |
| `coral` | `#F05465` | Late / error / destructive |
| `rose` | `#D45771` | Spectrum only |
| `violet` | `#62459B` | Focus ring, links, active tab |
| `royal` | `#3D5DAB` | Spectrum only, info |
| `ok` | `#1E9E6A` | Paid, verified, approved |

**Spectrum gradient** (left → right): amber → coral → rose → violet → royal. Use it for the
progress bar fill, the stepper's active connector, the splash, and at most one headline word per
screen. Never use it as a text background behind body copy.

**Status mapping** (the same everywhere):

| State | Colour | Dot/pill label |
|---|---|---|
| paid / verified / approved | `ok` | Paid · Verified · Approved |
| due (≤ 14 days) / pending / conditional | `amber` | Due · Under review · Conditional |
| late / rejected / blocked | `coral` | Late · Rejected |
| upcoming / not started | `muted` | Upcoming · Not started |

### Dark mode

This is Phase 2, but define the tokens now: background `#0B0D0F`, surface `#15181C`, ink `#F2F1EC`,
muted `#9A9AA3`, line `rgba(255,255,255,0.10)`. `nucleus` cards become `#000000` with a
`line` border. The accents stay the same.

### Typography

| Style | Font | Size / weight | Use |
|---|---|---|---|
| `display` | Bricolage Grotesque | 32 / 800, tracking -0.5 | Limit amount, splash |
| `headline` | Bricolage Grotesque | 24 / 700, height 1.1 | Screen titles |
| `title` | Bricolage Grotesque | 18 / 700 | Card titles |
| `body` | Inter | 16 / 400, height 1.5 | Body |
| `bodySmall` | Inter | 14 / 400 | Hints, secondary |
| `label` | Inter | 15 / 600 | Buttons (sentence case) |
| `eyebrow` | JetBrains Mono / system mono | 11 / 700, letter-spacing 1.8, UPPERCASE, `muted` | Section labels, field labels |
| `figure` | Inter, `FontFeature.tabularFigures()` | inherits | Every money amount and date in tables |

Bundle the fonts (don't fetch them at runtime) so first launch works offline.

### Shape & spacing

- Spacing scale: `4, 8, 12, 16, 20, 24, 32, 40`. Screen gutter is `20`.
- Radius: cards `20`, dark cards `24`, inputs `12`, buttons and pills are fully rounded (`999`).
- Elevation: none. Separate with `line` borders, not shadows. Dark cards may have a soft violet
  radial glow (like the web `.glow`).
- Minimum touch target is `48×48`.

## 2. Components

| Component | Spec |
|---|---|
| **PrimaryButton** | `nucleus` fill, white label, pill, height 52, full width in forms. Loading shows an inline spinner and keeps the width. |
| **GhostButton** | Transparent, `line` border, `ink` label. |
| **TextField** | `paper` fill, `line` border, 12 radius, focus border `violet` 2px. Label above in `eyebrow` style. Error text in `coral` below. |
| **CnicField** | Numeric keypad, mask `#####-#######-#`, validates 13 digits with the first digit 1–7. |
| **MobileField** | Phone keypad, mask `03## #######`, accepts pasted `+92…`. |
| **StatusPill** | Coloured dot + label on a 10% tint of the status colour. |
| **LimitCard** | `nucleus` card: eyebrow "AVAILABLE TO SPEND", `display` amount, spectrum progress bar (used/limit), then rows for Limit · Used · Max instalment · Tenure (web `.qline` style: white/80 text, white/10 dividers). |
| **ProcessStepper** | Vertical list of the 6 stages. The dot is `ok` if done, filled `violet` if current, `line` if upcoming, `coral` if blocked. The connector uses the spectrum up to the current stage. |
| **DocumentTile** | 4:3 thumbnail or dashed placeholder, label, "Retake"/"Upload" action, uploaded tick. |
| **EmptyState / ErrorView** | Atom logo mark, one sentence, one action ("Try again"). |
| **Banner** | Full-width inline message (info = royal tint, warn = amber tint, error = coral tint). |

## 3. Screens (Phase 1)

1. **Splash:** Nucleus background, atom logo (two orbit ellipses in spectrum gradients, white
   "A"), fade out as `GET /me` resolves.
2. **Sign in:** Logo, "Sign in with your AtomShop account", login field (email or mobile),
   password with show/hide, primary "Sign in", links for "Create account" and "Forgot password?".
2a. **Forgot password (native, 3 screens):**
   - Enter email or mobile, with a small note: "Mobile numbers get the code on WhatsApp".
   - A 6-box code input with a one-time-code autofill hint and a resend countdown from `resend_in`,
     showing the masked `destination`.
   - New password + confirm. Success lands signed in on Home, with a toast: "Password changed. It works
     on AtomShop.pk too."
   - Never say "no account found". Use the neutral "If this belongs to an AtomShop account…" copy.
3. **Register:** Name, mobile, email, password, confirm. A small print line: "This also creates your
   AtomShop.pk account."
4. **Home (Phase 1 placeholder):** Greeting (`short_name`), KYC status card with a CTA to the profile,
   and a "Shop on AtomShop.pk" button. Phase 2 replaces this with the dashboard.
5. **Profile:** Read view with a status pill, identity rows, 3 document tiles, and an "Edit" button.
6. **Profile form:** One scrolling form with three sections (Identity · Address · Documents)
   and a sticky bottom "Submit for review" button. On a verified profile, show a confirm dialog
   first: "Changing these details sends your profile back for verification."
7. **Account:** Name/email/phone (read-only), "Sign out", "Sign out of all devices"
   (destructive, `coral`, with confirmation).

Navigation: a bottom bar with **Home · Plans · Profile**, where Plans stays hidden until Phase 3. Sign-in and
register sit outside the shell.

## 4. Content & tone

- Plain, reassuring and short. Say "Your limit", not "Credit facility".
- Show money as `PKR 45,000`, never `Rs.`/`45000.00`.
- Dates look like `24 Oct 2026` in lists and `Thursday, 24 October` for next due.
- Errors say what to do next. For example: "That looks like a landline. Enter a mobile number so we
  can text you about payments."

## 5. Accessibility

- Contrast is AA minimum. `muted` on `paper` passes for ≥ 14 px, but don't use `amber` text on
  white (use amber only for dots, fills and tints).
- Every status is conveyed by text as well as colour.
- Supports text scale up to 2.0 and has Semantics labels on icon-only buttons.
- Respects reduced motion (no orbit animation on the splash).
