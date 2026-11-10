# Ask Atom

[← API index](README.md) · [Conventions, errors & limits](conventions.md)

Rules-based answers about AtomPay, the same engine as the website's Ask Atom (`AskAtomService`).
There is **no AI**, nothing is sent to a third party, and questions are not stored. Both endpoints are
public; a valid token makes answers link to signed-in screens (my application, my orders) rather than
sign-up.

**Voice:** the app transcribes on the phone and sends the text here. No audio is ever uploaded.

## `GET /ask-atom/chips`

The suggested questions shown before anything is typed.

```json
{ "data": ["What does AtomPay cost?", "How do I get a limit?", "Which products can I buy?", "When are my payments due?"] }
```

## `POST /ask-atom`

```json
{ "question": "Plan for 47,000" }
```

`question`: required, at most 300 characters. Rate limit: 30 a minute.

```json
{
  "data": {
    "paragraphs": [
      "On a Rs. 47,000 product: **Rs. 15,666 a month for 3 months**, with Rs. 15,668 in the last month so the three add up exactly.",
      "No advance, no markup, Rs. 47,000 repaid in total. Your limit has to cover the whole Rs. 47,000, because nothing is paid at checkout."
    ],
    "table": [
      { "label": "Month 1", "value": 15666 },
      { "label": "Month 2", "value": 15666 },
      { "label": "Month 3", "value": 15668 }
    ],
    "actions": [ { "label": "Products near this price", "route": "/shop", "url": null } ],
    "chips": ["What does AtomPay cost?", "How do I get a limit?", "Which products can I buy?", "When are my payments due?"]
  }
}
```

- **`paragraphs`** are plain text. `**bold**` is the only markup; render it bold. A paragraph starting
  `• ` is a list item.
- **`table`** is `null` unless the answer is a month-by-month plan. `value` is rupees.
- **`actions`**: exactly one of `route` (an app path: `/shop`, `/calculator`, `/help`, `/application`,
  `/application/status`, `/plans`, `/register`, `/login`, `/forgot`) or `url` (open in the browser:
  WhatsApp, a store, Atomshop.pk).
- An unrecognised question gets a helpful fallback, never an error.
