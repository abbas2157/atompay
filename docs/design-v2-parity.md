# v2 parity with the approved artifact

> The AtomPay v2 implementation against the design that was signed off:
> **claude.ai/artifact/5K8rN53VxBz3fY8U2EYXE6** ("AtomPay Smart Limit").
> Audited 9 Nov 2026 by diffing the artifact's own HTML and CSS against the
> rendered page, not by eye.

## The reference is the artifact, not atomshop.pk

The artifact was built *on* Atomshop v2's design system and redefines its
utilities inside its own `<style>`. That makes `site.css` the shared vocabulary
but **the artifact the authority on how AtomPay composes it.**

An earlier pass audited against `atomshop/resources/views/website/v2/**`
instead and "corrected" the page towards the shop's home: `.hero2` with its
cream gradient and 84px headline, an `#EEF2FD` apps band with 2×2 tiles,
28px feature tiles, 44/12 icon tiles, larger section headings, one-line store
buttons. **All of that was wrong and has been reverted.** Where the shop's
pages and the artifact disagree, the artifact wins.

## Property check

Every selector in the artifact's stylesheet exists in `site.css` or
`atompay.css`, and on the shared selectors there are **0 declaration
mismatches** — checked by parsing both and comparing property by property.
The one artifact selector with no counterpart is
`[hidden]:not([hidden=until-found i])`, part of the Artifact platform's own
skeleton reset rather than the design.

## Section check

| # | Artifact | Implementation | |
|---|---|---|---|
| 1 | `#top` `.section` | `#top` `.section` | match |
| 2 | `.section.tight.alt` stats | same | match |
| 3 | `#how` `.section` | same | match |
| 4 | `#plan` `.section.alt` | same | match |
| 5 | `#limit` `.section` | same | match + `.cta-row` |
| 6 | `#eligible` `.section.alt` | same | match, real products |
| 7 | `#ecosystem` `.section` | same | match |
| 8 | `#apply` `.section.alt` | same | match |
| 9 | `#account` `.section` | same | match |
| 10 | `#help` `.section.alt` | same | match + `.cta-row` |
| 11 | `.trust-band` | same | match |
| — | — | `#apps` `.section` | added |

Three artifact sections — `#apply`, `#account` and the `.trust-band` — were
**missing from the implementation entirely** before this pass. They are in now,
with the artifact's own components: the six application cards including the
apay-washed "What to have ready" tile, the account split with its example
schedule, and the Explore-more chip row.

## Deliberate differences, all requested after the artifact

1. **Four products per row.** The artifact's `.g-4` is `auto-fit minmax(230px,1fr)`,
   which lands on five at 1240px. `.grid.cols-4` counts columns instead.
2. **Real products** from `CatalogueService` in place of the artifact's
   JavaScript example cards, so the figures are live.
3. **Live estimators.** The plan section keeps the artifact's exact layout —
   field, three quick prices, ticks, schedule, `.card.soft` summary — and makes
   it recompute. The limit section keeps the `.spend` tile and gains an income
   field that drives it.
4. **Navbar trimmed to four links** — How it works · The plan · Eligible
   products · Help. The artifact had six; Your limit and Ecosystem are still
   sections, reached from the trust band and the footer.
5. **`#apps` section**, after Help, built from `.section` / `.sec-head` /
   `.split` / `.ticks` — the artifact had no apps section.
6. **Ask Atom triggers** on the limit and help sections.
7. **`#calculator` and `#assess` anchors** inside `#plan` and `#limit`, so v1
   links and `AssessmentController`'s redirect still land.

## One correction to site.css

`.badge` does not set `white-space: nowrap`, though
`atomshop/.claude/DESIGN.md` §1 rule 5 requires it — which is why "AtomPay
eligible" wrapped over two lines on a product card. Overridden in
`atompay.css` so `site.css` stays a verbatim, re-syncable copy. Worth fixing in
the shop too.
