# Products & plan preview

[← API index](README.md) · [Conventions, errors & limits](conventions.md)

The Shop tab: what Atomshop admin has marked **AtomPay eligible**, and AtomPay's plan for any price.
All public. Sending a token is optional: it only adds "does this fit my limit?" (`within_limit`,
`fits_limit`), which is `null` when signed out or without a limit.

Every figure is **AtomPay's own plan**: 3 months, no advance, 0% markup, monthly = `intdiv(price, 3)`,
and the last month takes the rounding. Don't use `/quote` (it prices Atomshop seller financing).

## `GET /products`

| Query | |
|---|---|
| `category` | a category `id` from `/products/categories` |
| `q` | search the title; every word must appear, in any order (`oppo a6` finds "Oppo A6K") |
| `within_limit` | `1` = only what the signed-in customer can still afford. Signed out or no limit → an empty list |
| `page` | 20 per page, featured first |

```json
{
  "data": [
    {
      "id": 51,
      "title": "OXY 3218L Android",
      "brand": "OXY",
      "category": { "id": 3, "title": "TVs" },
      "price": 47000,
      "monthly": 15666,
      "last_monthly": 15668,
      "months": 3,
      "image_url": "https://atomshop.pk/public/images/products/oxy-3218l.webp",
      "url": "https://atomshop.pk/oxy-3218l-android",
      "in_stock": true,
      "within_limit": true
    }
  ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 15 }
}
```

- `image_url` can be `null`: show the placeholder. Images come from Atomshop.pk.
- `url` is the product's page on Atomshop.pk: **Buy on Atomshop.pk** opens it in the browser. Nothing is
  bought in the app.

## `GET /products/categories`

Only categories with at least one eligible product.

```json
{ "data": [ { "id": 2, "title": "Mobile Phones", "count": 9 }, { "id": 3, "title": "TVs", "count": 6 } ] }
```

## `GET /products/{id}`

One product: the card above plus `plan`. `404` when it isn't (or is no longer) eligible.

```json
{
  "data": {
    "id": 51, "title": "OXY 3218L Android", "price": 47000, "monthly": 15666, "last_monthly": 15668, "…": "…",
    "plan": {
      "advance": 0, "markup": 0,
      "instalments": [15666, 15666, 15668],
      "total": 47000,
      "processing_fee_percent": 3, "processing_fee": 1410
    }
  }
}
```

## `GET /plan-preview`

`?price=47000` (whole rupees, 1 – 10,000,000). The calculator screen and Ask Atom's "plan for Rs. X".

```json
{
  "data": {
    "price": 47000, "advance": 0, "markup": 0, "months": 3,
    "instalments": [15666, 15666, 15668],
    "total": 47000,
    "processing_fee_percent": 3, "processing_fee": 1410,
    "fits_limit": true
  }
}
```

The 3% processing fee is shown with every plan as a disclaimer. Delivery charges are Atomshop's and
appear at checkout. Rate limit: 60 a minute.
