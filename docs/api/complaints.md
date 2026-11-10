# Complaints

[← API index](README.md) · [Conventions, errors & limits](conventions.md)

The in-app grievance route (SECP Circular 15/2022). A person reads every complaint and replies; the
customer quotes the `reference` to support. Complaints are kept when an account is deleted, linked to
the anonymised account, because the regulator can ask for them.

## `POST /complaints`

```json
{ "topic": "payment", "order_id": 1279, "message": "My December payment is not showing as paid." }
```

| Field | Rule |
|---|---|
| `topic` | required, a `value` from `GET /options` → `complaint_topics` |
| `order_id` | optional, one of **your own** orders (`GET /plans`). Anyone else's is a `422` |
| `message` | required, 10 – 2000 characters |

**`201 Created`**: go to the "Complaint sent" screen with the reference and a Copy button.

```json
{
  "data": {
    "id": 42,
    "reference": "CMP-0042",
    "topic": "payment",
    "topic_label": "A payment",
    "order_id": 1279,
    "order_number": "AS-01279",
    "message": "My December payment is not showing as paid.",
    "status": "open",
    "reply": null,
    "replied_at": null,
    "created_at": "2026-11-10T10:00:00+05:00"
  }
}
```

`422` field errors as usual. `429` after 5 complaints in an hour per account. Support is emailed at
`ATOMPAY_SUPPORT_EMAIL` when one is set; the complaint is saved either way.

## `GET /complaints`

Your complaints, newest first, in the same shape. `status` is `open`, `answered` (see `reply`) or `closed`.

> Replying is done by staff. There is no admin screen for it yet; until there is, replies are written to
> `atompay_complaints.reply` / `status` / `replied_at` directly.
