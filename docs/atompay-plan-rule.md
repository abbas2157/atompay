# AtomPay's plan is not seller financing

> Written 9 Nov 2026, while building the v2 design. **The v1 site still quotes the wrong plan.**

## The rule

AtomShop's owner fixed AtomPay's terms on **24 October 2026**. On
`atomshop/.claude/MEMORY.md`:

> **AtomPay is offered only on eligible listed products, always 3 months, no advance, 0% markup (owner).**
> Eligibility is `products.atompay_eligible` or `brands.atompay_eligible` (admin v2 toggles, default off).
> `instalment_perform` refuses AtomPay on other products (422) and ignores the browser's tenure/advance for AtomPay.

So an AtomPay plan is:

| | |
|---|---|
| Term | **3 months**, always. Not chosen by the customer. |
| Advance | **Rs. 0**. None is taken at checkout. |
| Markup | **0%**. The customer repays exactly the cash price. |
| Monthly | `intdiv(price, 3)`, with the **last month taking the rounding** so the three add up to the price. |
| Processing fee | **3% of price**, shown with every plan as a *disclaimer*. Never added to an order total. |
| Delivery | Charges apply, stated alongside the fee. |
| Eligibility | Selected listed products only, per product or per brand. **Never** custom orders or offers. |
| Requires | An approved AtomPay limit covering the **whole** price, because no advance reduces it. |

AtomShop implements this in `Website\InstallmentPlanService::atompayPlan()`
and re-prices every order server-side, so the shop is the authority.

## What AtomPay's own site does instead

`InstalmentQuoteService` and the `plan` block of `config/atompay.php` price
**AtomShop seller financing** — a different product:

```php
'min_advance_ratio' => 0.20,   // 20-60% down payment
'max_advance_ratio' => 0.60,
'default_tenures'   => [3, 6, 9, 12],
'default_per_month' => 4.0,    // 4% per month on the financed amount
```

`markup = per_month% × months × (price − advance)`.

That is correct for the plan a **seller** finances, and the v1 landing
calculator, `POST /quote` and the mobile `plan` endpoint all quote it. It is
not what a customer gets when they pick AtomPay at checkout: they get three
months at zero markup. On a Rs. 100,000 product the two differ by Rs. 19,200
of markup and a Rs. 20,000 advance.

## What has been done about it

- `config('atompay.atompay_plan')` now holds the real rule, with the
  seller-financing `plan` block left alone and a comment on each saying which
  product it prices.
- `InstalmentQuoteService::atompayPlan()` implements it, mirroring AtomShop's
  method, and `tests/Unit/AtompayPlanTest` pins every figure including the
  rounding month.
- The **v2** landing page quotes `atompayPlan()`. The **v1** landing page still
  quotes seller financing.

## Still to decide

1. **The v1 page.** Either correct its calculator or retire v1 once v2 covers
   every page. Until then the live site offers a plan AtomPay does not sell.
2. ~~**`POST /quote` and the mobile `plan` endpoint.**~~ **Done 12 Nov 2026.**
   `POST /quote`, `POST /api/v1/quote` and `GET /api/v1/calculator` now answer
   with AtomPay's plan only (`InstalmentQuoteService::atompayQuote()`), in the
   same response shape so older app builds show the right figures unchanged.
3. **The processing fee.** Carried in two places now
   (`atompay.atompay_plan.processing_fee_percent` here,
   `website.atompay.processing_fee_percent` in AtomShop). They must not drift;
   the shop's value wins, because the shop prices the order.
4. **AtomPay's accent colour.** Unrelated to the maths but found at the same
   time: AtomShop's product page dresses its AtomPay plan box in violet
   (`#5A3FA6`), while its `/apps` page uses the AtomPay logo's amber-to-coral
   (`linear-gradient(135deg,#FAA53A,#F05465)`). The logo says amber-to-coral,
   which is what AtomPay v2 uses.
