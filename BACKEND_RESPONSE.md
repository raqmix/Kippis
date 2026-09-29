# Backend response to the Foodics / ordering handoff

Replying to `BACKEND_HANDOFF.md` (2026-09-29). Written for the mobile team.

Issues 1 and 2 are fixed and deployed. Issue 3 is not a backend problem. Issue 4 has a code-level cause we found while investigating, but the data side still needs someone with portal access.

Nothing below asks for a client change. Where an API contract is involved, it is unchanged.

---

## 1. Discounted order charged at full price — fixed

### What was wrong

Two separate faults, both on the backend.

**Fault A — the Foodics payload contradicted itself.** We send product lines at their full, undiscounted prices and send the discount separately as `discount_amount`. Foodics is expected to subtract one from the other. But we were also sending `total` already discounted:

| Field we sent | Value |
|---|---|
| Product lines | 25.00 |
| `discount_amount` | 3.75 |
| `total` | **21.25** ← wrong |

Foodics computed `25.00 − 3.75 = 21.25`, then received a declared `total` of 21.25 that already had the discount applied. It reconciled the contradiction by treating the difference as change — which is exactly what the receipt showed: **25.00 paid, 3.75 change**.

**Fault B — the cart sometimes stored a zero discount.** `Cart::recalculate()` read the `promoCode` relation without guaranteeing it was loaded and current. On some paths the relation was absent or stale, so the promo appeared applied in the UI while `discount` was persisted as `0`. Any order created in that state was charged the undiscounted amount.

Fault B is the more likely explanation for the bank charging 25.00 rather than 21.25. The charge amount itself is taken from `cart.total`, which is correct code — it just had the wrong number in it when the discount failed to persist.

### What changed

- `PushOrderToFoodics` now sends `total` pre-discount, matching the product lines it sends alongside.
- Added a reconciliation guard: if `total − discount` ever stops equalling the amount charged, we log `FOODICS_PUSH_TOTAL_MISMATCH` rather than let a wrong receipt print.
- `Cart::recalculate()` now force-reloads `promoCode` and verifies the loaded relation matches the current `promo_code_id`.

### Verification

Nine tests, run against the reported figures (25.00 item, 3.75 discount). We also temporarily reverted the fix to confirm the tests fail without it — they do.

Production spot-check across several recent orders: `Money OK` on all of them, Foodics totals matching the amounts charged.

### What the client does not need to change

`CheckoutDto` correctly omits the charge amount. The backend calculates and validates it, which is the right split. Keep it that way.

### Open item for whoever owns support

Orders placed **before this deployment** may have been overcharged. We have not audited historical orders — that needs database access plus a decision on how to handle refunds. Order #104 / Foodics check #102945 is the known case. Flag this to whoever owns customer support.

---

## 2. Closed Foodics order stays active for the guest — fixed

### What was wrong

This one was ours, not Foodics'.

When staff close payment on the POS, Foodics settles the check and stamps a `closed_at` timestamp — but it leaves `status` on whatever preparation value it last held. Our webhook and our polling fallback both read `status` and nothing else, so neither could ever observe the close.

We confirmed this against live orders. One example:

```
closed_at : '2026-07-14 13:56:12'   ← settled in Foodics
status    : 4  → maps to 'mixing'   ← never moved
```

That order was closed in July and was still active in the app in late September. There were 82 of them.

Your reading in the handoff was right: the client cannot infer a close that the endpoints do not reflect. The endpoints were not reflecting it.

### What changed

Added `FoodicsStatusMapper::fromFoodicsOrder()`, which reads the whole order rather than just `status`:

- `closed_at` present → **completed**, regardless of what `status` says
- Status is `cancelled` → stays **cancelled** (a cancelled order is also closed, and cancelled is the more accurate terminal state)
- Check still open → keeps its normal preparation status

It also accepts `is_paid` and a non-empty `payments[]` as settlement signals, so this keeps working if a branch or API version reports it differently.

Wired into both the polling command and the webhook. The webhook additionally **stopped rejecting** payment events that carry no `status` — it was returning 422 on them, so any such event was being discarded.

### Verification

Six tests using the live payloads above. On deployment the stuck backlog dropped from **82 to 66** within one polling cycle — those 16 were orders Foodics had marked closed that we had never picked up. The remainder are still working through the poll.

### What this means for the client

No change needed. `GET /orders/active` will now stop returning these orders, and the Live Activity will receive a terminal status on its next refresh. The 15-second poll interval is fine.

### Answering your lifecycle question

> *Confirm the intended lifecycle mapping [...] and whether "close payment" should mean `completed`.*

Yes — closing payment now means `completed`. Foodics status codes map as: `1,2,3 → received`, `4 → mixing`, `5 → ready`, `6 → completed`, `7 → cancelled`. A settled check overrides all of these except `cancelled`.

Replaying a Foodics event does not reopen an order: the webhook skips the update when the status is unchanged, and the poll skips terminal orders entirely.

---

## 3. Branch POS cannot advance the status sequence — not a backend issue

We agree with your assessment. Nothing in the Kippis backend limits what the Foodics POS displays — that is Foodics' own workflow configuration and the branch account's permissions.

The backend already accepts every stage in the sequence, from either the webhook or the poll. If Foodics can be configured to emit them, they will flow through to guest tracking with no backend change.

If Foodics genuinely cannot expose the intermediate stages, the fallback is a staff-facing action in the Kippis portal. That is a real piece of work — new endpoint, permission model, audit trail — and it needs a product decision before it is worth building. It is not blocked on anything technical.

---

## 4. Branch hours and availability — cause found, data work still outstanding

### A real backend bug, in addition to the data problem

While checking this we found that branch hours are compared against **UTC**, not Cairo time:

- `config/app.php` defaults `APP_TIMEZONE` to `UTC`, and `.env.example` does not override it
- `Store::isOpenNow()` compares the configured `open_time`/`close_time` against `now()`

So a branch configured as 9:00 AM–11:00 PM is evaluated against UTC. Egypt is UTC+2 (UTC+3 during DST), which means `is_open_now` is wrong by two to three hours at the boundaries. A branch reads as closed for its first hours of business and stays open past closing.

This needs fixing before the hours data is entered, otherwise correct data will still produce wrong behaviour. Two options, and we would like your input since it affects what the client displays:

1. Set `APP_TIMEZONE=Africa/Cairo` — simplest, but it shifts every timestamp in the system, so it needs a careful look at anything storing or comparing times.
2. Make `isOpenNow()` timezone-aware on its own, leaving the rest of the app on UTC — narrower, safer, but means the hours logic carries the timezone knowledge locally.

We have not applied either yet.

### The empty `-` you are seeing

That is `open_time`/`close_time` being null in the database. And there is a trap worth knowing about:

```php
if (!$this->open_time || !$this->close_time) {
    return true; // If no hours set, assume always open
}
```

A branch with no hours configured reports `is_open_now: true` — permanently open. So the branches showing `-` are not just missing a display value, they are also bypassing the closed-branch check at checkout.

### What still needs doing, and who can do it

The rest is data entry in the admin portal, not code:

- Set hours on the branches currently showing `-`
- Mark D5/District 5, Sahel, Events, and U Venues inactive
- Confirm whether "Events" and "U Venues" are two records or one
- Add Ignite Melanite and verify the `B09` Foodics reference maps correctly

We do not have portal access, so we cannot verify the store IDs or make these changes. Whoever owns the portal should do this — and the timezone fix should land first.

### API contract — unchanged

`GET /stores` continues to return `open_time`, `close_time`, and `is_open_now`. Keep reading them as you do now. Once the data is entered and the timezone issue is resolved, `is_open_now` will match Egypt local time.

Checkout already rejects a closed branch with `BRANCH_CLOSED` (HTTP 422), so a stale client selecting an inactive branch gets a clear error rather than a silent failure.

---

## Deployment status

| Issue | Status |
|---|---|
| 1 — Discount / charge amount | Deployed and verified |
| 2 — Closed order stays active | Deployed; backlog clearing |
| 3 — POS status sequence | No backend work applicable |
| 4 — Branch hours | Timezone bug found, not yet fixed; data entry still outstanding |

### Diagnostic tooling

Two commands were added for verifying this on the server:

- `php artisan foodics:inspect-order <id>` — compares a Kippis order against the live Foodics check, reporting money and lifecycle mismatches. Read-only; makes no database writes and no writes to Foodics.
- `php artisan foodics:doctor` — verifies the integration protections are live. Exits non-zero on failure, so it works in a deploy script.

### Related work, for context

Separately from your handoff, we resolved a rate-limiting problem with Foodics: our integration was generating roughly 29,000 rejected requests per week against their 90-per-minute limit. Request volume is now approximately 170/hour, down from 1,800. This is unrelated to the four issues above but was affecting the same integration, so it is worth knowing about.

---

## What we need from you

Nothing blocking. Two things when convenient:

1. **Issue 4 timezone** — a view on which of the two options you prefer, since it affects displayed hours.
2. **Issue 3** — if the Foodics POS cannot be configured to expose the stages, tell us and we will scope the portal-side alternative.

If you see either issue 1 or 2 recur after this deployment, send us the order ID and we will run `foodics:inspect-order` against it.
