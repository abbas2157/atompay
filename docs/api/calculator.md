# Calculator & estimate

[← API index](README.md) · [Conventions, errors & limits](conventions.md)

> **The v2 app uses [`GET /plan-preview`](products.md#get-plan-preview)** for AtomPay's plan. `/calculator` and
> `/quote` return the same plan in the shape older builds were written against; `/estimate` is the income → limit
> estimate.

All three are public, so they work before sign-up.

`/calculator` and `/quote` price **AtomPay's own plan**, the one checkout charges: always 3 months, no advance,
0% markup. The months add up to the price exactly, because the last month takes the rounding. A 3% processing
fee is shown with the plan as a disclaimer and is never added to `total`. Until 2026-11-12 these two endpoints
priced Atomshop seller financing, which AtomPay does not sell.

## `GET /calculator`

The bounds for the calculator controls:

```json
{
  "data": {
    "plan": "atompay",
    "tenures": [3],
    "per_month_percentage": 0,
    "advance": { "min_ratio": 0, "max_ratio": 0 },
    "processing_fee_percent": 3,
    "price": { "min": 10000, "max": 300000, "step": 5000 }
  }
}
```

## `POST /quote`

```json
{ "price": 100000, "months": 3 }
```

`months` must be `3`. `advance` is optional; if sent, it must be `0`.

```json
{
  "data": {
    "plan": "atompay",
    "price": 100000, "advance": 0, "months": 3, "per_month_percentage": 0,
    "financed": 100000, "markup": 0, "total": 100000,
    "monthly": 33333, "last_monthly": 33334,
    "processing_fee": 3000, "processing_fee_percent": 3,
    "advance_bounds": { "min": 0, "max": 0 }
  }
}
```

`monthly` is months 1 and 2; `last_monthly` is month 3, which takes the rounding.

**`422`** returns field errors: `months` gets *"AtomPay plans are always 3 months."*, and `advance` gets
*"AtomPay takes no down payment."*

It's limited to 60 calls/min.

## `POST /estimate`

A "what could I get?" preview from income alone. Nothing is saved, and it is **not** an application.

```json
{ "monthly_income": 150000 }
```
```json
{ "data": { "monthly_income": 150000, "estimated_limit": 45000, "estimated_max_instalment": 15000 } }
```

Always label these figures as an estimate in the UI.
