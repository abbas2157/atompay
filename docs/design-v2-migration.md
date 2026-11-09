# v1 → v2: the page map

> Every view AtomPay renders, which design serves it, and what is left.
> Updated 9 Nov 2026.

How the switch works is in the README. The short version: controllers call
`atompay_view('home.index')`, which renders `v2.home.index` when
`ATOMPAY_DESIGN=v2` **and** that view exists, and otherwise the v1 view at its
original path. Nothing was moved, so an unmigrated page keeps working.

Three kinds of row appear below:

- **v2** — a v2 view exists and is used.
- **shared** — one copy of the content, two shells. Used where the markup was
  already semantic (error pages, emails, the long policy texts): forking it
  would mean two copies of words that must not drift apart.
- **v1 only** — still renders its v1 view in both designs.

---

## Public pages

| URL | Controller | v1 view | v2 view | Status |
|---|---|---|---|---|
| `/` | `Web\HomeController@index` | `home.index` | `v2.home.index` | **v2** |
| `/faq` | `Web\HomeController@faq` | `pages.faq` | `v2.pages.faq` | **v2** |
| `/products` | `Web\ProductController@index` | — | `v2.pages.products` | **v2 only** |
| `/privacy` | `Web\HomeController@privacy` | `pages.privacy` | `v2.pages.privacy` | **v2**, text shared |
| `/delete-account` | `Account\DeleteAccountController@info` | `pages.delete-account` | `v2.pages.delete-account` | **v2**, text shared |
| `/sitemap.xml` | `Web\SitemapController` | `seo.sitemap` | — | XML, no design |
| `/robots.txt` | `Web\RobotsController` | none | — | generated, no design |

The two policy texts live in `pages/partials/privacy-body` and
`pages/partials/delete-account-body`. Their markup is semantic and class-free
(`<h3>`, `<p>`, `<ul>`, `<strong>`), so v1 wraps them in its Tailwind prose
container and v2 in `.prose`. **Edit the partial, not a view** — and update the
"Last updated" date in both views.

## Auth

All noindex. Shell: `v2.layout.auth` — form left, the shared "one account"
panel right, the same shape as Atomshop's `website/v2/auth/layout`.

| URL | v1 view | v2 view | Status |
|---|---|---|---|
| `/login` | `auth.login` | `v2.auth.login` | **v2** |
| `/register` | `auth.register` | `v2.auth.register` | **v2** |
| `/register/verify` | `auth.register-verify` | `v2.auth.register-verify` | **v2** |
| `/forgot-password` | `auth.forgot-password` | `v2.auth.forgot-password` | **v2** |
| `/forgot-password/verify` | `auth.verify-code` | `v2.auth.verify-code` | **v2** |
| `/reset-password` | `auth.reset-password` | `v2.auth.reset-password` | **v2** |

The resend cool-off was an Alpine countdown in v1; in v2 it is
`<form data-resend="45">` driven by `site.js`. The server enforces the same
window either way, so the button is a courtesy, not the control.

## My AtomPay (customer, noindex)

Shell: `v2.layout.account` — page hero, sticky sidebar (`v2.account.nav`),
`.acct-main` column.

| URL | Controller | v1 view | v2 view | Status |
|---|---|---|---|---|
| `/my` | `Account\DashboardController@index` | `account.dashboard` | `v2.account.dashboard` | **v2** |
| `/my/applications` | `@applications` | — | `v2.account.applications` | **v2 only** |
| `/my/verification` | `@verification` | — | `v2.account.verification` | **v2 only** |
| `/my/orders` | `@orders` | — | `v2.account.orders` | **v2 only** |
| `/my/delete` | `Account\DeleteAccountController@show` | `account.delete` | `v2.account.delete` | **v2** |
| `/my/application` | `Account\ApplicationController@create` | `account.application` | `v2.account.application` | **both** |

The three **v2 only** pages are a v2 idea: v1 puts all of it on `/my` on
purpose. In v1 those URLs redirect to `/my` (`DashboardController::v2Only()`)
rather than render a view that does not exist.

Every customer-facing page now has a v2 view; only the internal staff screens stay on v1 — see **What is left** below.

## Errors

Content shared, shell by design. Each view opens with
`<x-dynamic-component :component="design_is_v2() ? 'v2.error-layout' : 'layouts.error'">`,
so the words exist once and only the chrome changes. Both shells are
self-contained — inline CSS, no stylesheet, no web font, no script, no auth
lookup, no query — because an error page is often rendered *because* one of
those is broken. `.btn-primary` / `.btn-ghost` are aliased in the v2 shell so
the shared markup works in both.

`401` · `403` · `404` · `419` · `429` · `500` · `503` — all **shared**.

**One limitation, by design.** An unmatched URL never reaches the `web`
middleware group, so `SetDesign` does not run for a routing 404 and there is no
session to read a `?design=` preview from. Error pages therefore follow the
**live** design (`config('design.default')`), which is what visitors and
crawlers see — correct in production, but it means a reviewer previewing v2
with `?design=v2` still gets the v1 shell on a 404. Pinned by
`test_error_pages_follow_the_live_design_without_the_middleware`, because the
failure is silent: the page renders fine, just in the other design.

## Emails

Content shared, shell by design, through `design_view('emails.layout')` and
`design_view('emails.partials.button')`. Mail renders with no request in front
of it, so emails follow the site's **live** design, never a `?design=` preview.

| Mail view | Shell |
|---|---|
| `emails.signup-code`, `emails.password-code`, `emails.password-changed`, `emails.welcome`, `emails.alert`, `emails.alerts-updated`, `emails.account-deleted` | `v2.emails.layout` when v2 is live |

The v2 shell is navy with the amber-to-coral rule; inline styles only and no
images, which rules out the PNG mark, so the brand is a navy cell with an "A".
**Known nit:** a few greys inside the mail bodies are still v1's warm
`#6C6C74` / `#FBFAF7` rather than v2's cool `#69738C` / `#F5F7FB`. They sit
inline in the shared content, and two hex values did not justify forking six
files of copy.

## Staff (internal)

| URL | View | Status |
|---|---|---|
| `/staff/assessments`, `/staff/assessments/{id}` | `staff.assessments.*` | **v1 only, on purpose** |

Internal review screens, never seen by a customer. AtomShop keeps its admin out
of its own v1/v2 switch for the same reason.

## v1-only partials, superseded

Not gaps — v2 writes its own markup for these, so they are v1's and stay v1's:
`home.partials.*` (hero, relation, how, calculator, why, assess),
`account.partials.*` (limit-card, plan-card, verify-banner),
`account.application.*`, `forms.assessment`.

---

## v2 is live

`ATOMPAY_DESIGN=v2` since 9 November 2026, with `config('design.default')`
changed to match so a deploy without the env var still serves v2.

Flipping it surfaced three things v2 had quietly lost, all now fixed:

1. **Outbound shop links had no UTM tags.** The v2 views used `$shopUrl`
   (the bare address) where v1 used `$shopLink` (which carries
   `utm_source=atompay&utm_medium=referral`), so AtomShop's analytics would have
   stopped crediting AtomPay for referrals. Structured data keeps `$shopUrl` -
   a canonical URL must never carry campaign tags.
2. **WhatsApp links were built on the local number.** v2 wrote
   `wa.me/03302277522`, which does not resolve: wa.me wants the international
   form. Both designs now call `Pakistan::whatsappUrl()`, which converts
   `03XX` to `923XX` and attaches the prefilled message.
3. **The share image was the logo PNG.** The v2 home overrode `og:image` with
   `atompay-logo.png` instead of the 1200x630 `og-default.jpg`, so a shared
   link would have previewed as a logo with no declared dimensions.

Two tests also had to stop asserting one design's markup as if it were the
only one: the cookie banner (v1 builds it with Alpine, v2 with `site.js`) and a
pending order's "what happens next" (v1 lists orders on `/my`, v2 on
`/my/orders`). Both now check the behaviour in both designs.

And `AnalyticsTest` could no longer assert that the CSP contains no "google"
at all, because v2 carries `fonts.googleapis.com` and `fonts.gstatic.com` for
Plus Jakarta Sans. It now names the three analytics hosts instead - and
self-hosting the font would remove the whole question.

## What is left

**Nothing customer-facing.** Every public, auth and account page has a v2 view;
`DesignSwitchTest::test_every_customer_facing_view_has_a_v2_twin` asserts it, so
a new page that forgets its v2 twin fails the suite rather than silently serving
v1.

**The staff review screens stay on v1 on purpose.** `/staff/assessments` and
`/staff/assessments/{assessment}` render through `x-layouts.account`, which has
no design awareness, and they are reached with a plain `view()` rather than
`atompay_view()`. They are internal — only `config('atompay.staff_roles')` can
open them — so they are not part of the customer-facing design and were left
alone. `test_a_page_without_a_v2_view_falls_back_to_v1` points at them, which
also keeps the fallback itself covered.

### How `/my/application` was ported (9 Nov 2026)

The 4-step KYC and income wizard was the last customer page on v1. v1 drove it
with Alpine (`applicationWizard` in `resources/js/app.js`); v2 loads no Alpine,
so the behaviour was rewritten as vanilla JS in `site.js` behind
`[data-kyc-wizard]`, and the view is `v2/account/application.blade.php`.

What carried over unchanged: one `<form>` holding all four steps, posted once,
so `KycApplicationRequest` is still the single source of validation; the step
that owns a server-side error is reopened (`data-error-step`); the furthest step
reached stays reachable (`data-furthest`); the limit, cap and disposable income
preview live from the income fields, with the ratios read from
`data-limit-ratio` / `data-cap-ratio` so `config/atompay.php` remains the one
place they are set.

What is better than v1: the page now degrades without JavaScript. v1 hid every
step behind `x-cloak`, so with Alpine blocked a customer saw nothing; the v2
markup renders all four steps open, hides *Continue* rather than *Submit*, and
shows the employer field whenever the chosen status has one
(`EmploymentStatus::hasEmployer()` mirrored in Blade) — so `Rule::requiredIf`
can never demand a field the customer cannot see. `site.js` then hides what it
needs to, behind site.css's `is-ready` loader, so there is no flash.

---

## The eligible catalogue

`/products` and the row on the landing page show what **admin has already
selected on AtomShop**: `products.atompay_eligible`, or `brands.atompay_eligible`
for a whole catalogue. No new flag and no migration — eligibility already *means*
"can be bought with AtomPay", so listing exactly those products is both correct
and needs nothing from AtomPay but a read. AtomPay writes neither column;
`Product` and `Brand` carry `BelongsToAtomShop`, which guards every attribute.

`CatalogueService` hangs each product's AtomPay plan on it
(`InstalmentQuoteService::atompayPlan`), so no view does arithmetic and a card
can never quote a figure that differs from checkout. Admin's existing
`feature` / `web_home` flags order the listing. Cards link to
`atomshop.pk/{slug}` — the order is placed there, never here.

**Fixed on the way:** `Product::shopUrl` built `/product/{slug}`, but AtomShop
serves a product at the root — `/{slug}`, `website.product.detail`. That is one
segment, so every link AtomPay built from it 404'd.

If you later want a smaller curated showcase rather than the whole eligible set,
that needs `products.atompay_home` (beside the existing `web_home` / `app_home`)
plus an admin screen — a migration in the **atomshop** repo, since AtomPay must
never migrate shop tables.

## Latent edge case worth a look

`ObligationService::exposure()` adds the financed amount of orders that are
`Processing` or `Delivered` to the sum of unpaid instalments, on the assumption
that such an order has no schedule yet. That holds in AtomShop's real flow,
where the schedule is generated when the order reaches `Instalments`. But an
order that is `Delivered` **and** already carries a schedule is counted twice,
halving the customer's apparent available limit. Found while writing
`AtomshopEndToEndTest`, which initially created exactly that state. Worth either
excluding orders that have instalment rows, or asserting the invariant.

---

Also open, from the same pass:

1. **The v1 calculator quotes the wrong plan** — see
   [atompay-plan-rule.md](atompay-plan-rule.md). `POST /quote` and the mobile
   `plan` endpoint have the same problem.
2. **Self-host Plus Jakarta Sans.** v2 pulls it from Google Fonts, which costs
   two CSP hosts and one render-blocking request. v1 self-hosts its fonts
   through Vite; v2 should too.
3. **`/delete-account` is not in the sitemap**, although it is public and the
   app-store listings link to it.
