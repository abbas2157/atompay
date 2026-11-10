# Plans

[← API index](README.md) · [Conventions, errors & limits](conventions.md)

The customer's AtomShop orders, in every status, with their instalment plans.

## `GET /plans`

Returns **every** order the customer has placed, newest first, whatever its status: waiting for
approval, being repaid, repaid or cancelled. Group or filter them in the app by `state` (for example
an "Active" tab and a "History" tab). AtomPay-financed orders are included. `?include=completed` is
no longer needed and is ignored.

```json
{
  "data": [
    {
      "order": {
        "id": 1043, "reference": "AS-01043", "status": "Instalments", "status_label": "Instalments",
        "ordered_at": "2026-07-02T10:15:00+00:00",
        "total_price": 140000, "advance": 24000, "financed": 116000, "tenure": 6
      },
      "product": {
        "id": 47, "title": "Poco C75 8GB RAM",
        "picture_url": "https://atomshop.pk/uploads/…jpg",
        "shop_url": "https://atomshop.pk/product/poco-c75"
      },
      "state": "on_track",
      "progress": {
        "paid_count": 2, "total_count": 6, "paid_amount": 38666, "total_amount": 116000,
        "remaining_amount": 77334, "percent": 33
      },
      "next_due": { "...": "instalment object, or null" }
    }
  ]
}
```

- `state` is one of:

  | `state` | Meaning | Suggested pill |
  |---|---|---|
  | `pending` | Waiting for AtomShop's approval (`order.status` `Pending` or `Varification`). No schedule yet. | amber, "Awaiting approval" / "In verification" |
  | `processing` | Approved (`Processing` or `Delivered`) but the schedule isn't set yet. | amber, `order.status_label` |
  | `on_track` | Being repaid, nothing overdue | green |
  | `late` | Being repaid, an instalment is overdue | coral |
  | `completed` | Fully repaid | green |
  | `cancelled` | Cancelled. Never `late`, even if unpaid rows remain | grey |

- When `progress.total_count` is `0` (`pending`, `processing`, usually `cancelled`), there is no
  schedule to show yet. Show `order.total_price`, `order.advance`, `order.financed` and `order.tenure`
  instead, with a line about what happens next.
- `order.status_label` is the display form of `order.status` (`Varification` is shown as "Verification").
- `product` can be `null` if the AtomShop product was removed.
- `product.picture_url` is public (served by AtomShop), so no auth header is needed.

## `GET /plans/{order_id}`

Returns the same object plus the full schedule. It returns `404` if the order isn't the customer's.

```json
{
  "data": {
    "order": { "...": "..." }, "product": { "...": "..." }, "state": "late", "progress": { "...": "..." },
    "next_due": { "...": "..." },
    "instalments": [
      { "id": 811, "order_id": 1043, "label": "Instalment 1", "due_date": "2026-08-05",
        "amount": 19334, "paid_amount": 19334, "paid_on": "2026-08-04", "state": "paid" },
      { "id": 812, "order_id": 1043, "label": "Instalment 2", "due_date": "2026-09-05",
        "amount": 19334, "paid_amount": null, "paid_on": null, "state": "late" }
    ]
  }
}
```

`label` is AtomShop's own text (e.g. "1st Instalment"), so display it as it is.

Instalment `state` and colour:

| State | Meaning | Colour |
|---|---|---|
| `paid` | Paid | green |
| `late` | Past its due date and unpaid | coral |
| `due` | Due within 14 days | amber |
| `upcoming` | Due later | muted |

Payments are made through AtomShop's existing channels. The app only shows status.

## `GET /plans/{order_id}/kfs`

The **Key Fact Statement** of one of your AtomPay orders (SECP Circular 15/2022). `404` for an order
that isn't yours or isn't an AtomPay order (seller financing has no KFS here).

```json
{
  "data": {
    "order_id": 1279,
    "order_number": "AS-01279",
    "product": "OXY 3218L Android",
    "lender": { "name": null, "licence": null, "address": null },
    "amount_financed": 47000,
    "advance": 0,
    "markup_percent": 0,
    "processing_fee_percent": 3,
    "processing_fee": 1410,
    "apr_percent": 18.5,
    "months": 3,
    "schedule_final": true,
    "instalments": [
      { "number": 1, "due_date": "2026-12-10", "amount": 15666 },
      { "number": 2, "due_date": "2027-01-10", "amount": 15666 },
      { "number": 3, "due_date": "2027-02-10", "amount": 15668 }
    ],
    "total_repayable": 47000,
    "late_payment": null,
    "early_settlement": null,
    "complaints": "In the app: Help › Complaints. If you are not satisfied with our answer, you can complain to the SECP.",
    "issued_at": "2026-11-10T10:00:00+05:00"
  }
}
```

- **`null` means "not supplied yet"** (lender identity, late-payment and early-settlement terms, set in
  `.env`). Show a visible placeholder, never a guess.
- **`schedule_final: false`** while the order awaits approval: the instalments are AtomPay's plan for
  the amount, with `due_date: null` ("set when your order is delivered").
- **`apr_percent`** is the nominal annual rate that makes the monthly payments worth the amount financed
  less the processing fee: 18.5% with the 3% fee on three equal months, 0 with no fee.

## `POST /plans/{order_id}/kfs/send`

Emails a copy to the address on the account. `{ "channel": "email" }` (the only channel today).

| Status | When |
|---|---|
| `202` | Sent |
| `409` `no_email` | Mobile-only account. Show `message` (add an email on Atomshop.pk, or take a screenshot). |
| `422` | Unknown `channel` |
| `429` | 3 copies an hour per account |
| `503` | The mail server failed. Show `message` and let them retry. |

Share it with the system share sheet as well; never save it to the gallery (that needs a storage
permission lending apps may not hold).
