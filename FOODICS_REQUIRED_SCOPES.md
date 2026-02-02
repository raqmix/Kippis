# Foodics Required Scopes

This document lists the minimal Foodics OAuth scopes required by the current Kippis backend integration plus the scopes we must request to support kiosk orders and app/website orders.

Foodics scopes reference (official): `https://apidocs.foodics.com/core/scopes.html`

## Current Integration (Read-Only Sync)
The backend currently performs **read-only** syncs of:
- Categories (`GET v5/categories`)
- Products (`GET v5/products`)
- Branches (`GET v5/branches`)

### Required Scopes
We only need `general.read` to access the endpoints above.

| Foodics Resource | Endpoint(s) Used | Required Scope | Why |
| --- | --- | --- | --- |
| Categories | `GET v5/categories` | `general.read` | Read categories for menu sync |
| Products | `GET v5/products` | `general.read` | Read products for menu sync |
| Branches | `GET v5/branches` | `general.read` | Read branches for store sync |

## Orders (Kiosk + App/Website)
To create and manage customer orders from the kiosk and online channels, Foodics lists the following order-related scopes. We should request the **limited** order scopes to keep access tight, and only add full `orders.get` / `orders.write` if Foodics requires it for a specific operation.

### Minimum Scope Set (Recommended)
| Action | Foodics Scope | Why |
| --- | --- | --- |
| List orders | `orders.list` | Needed if we show order history or reconcile |
| Read order | `orders.limited.read` | Fetch order details/status |
| Create order | `orders.limited.create` | Create kiosk/app/website orders |
| Pay order (if we capture payment in Foodics) | `orders.limited.pay` | Mark order as paid |
| Deliver/fulfill order | `orders.limited.deliver` | Mark order as delivered/fulfilled |
| Decline/cancel order | `orders.limited.decline` | Decline orders when needed |

### Full Access Alternative (Only If Required by Foodics)
Foodics also lists `orders.get` and `orders.write` for broader access. If Foodics requires these for create/update/pay flows, replace the limited scopes above with:
- `orders.get` (read)
- `orders.write` (create/update/pay/deliver/decline)

## Notes
- No write scopes are required for menu data because we only read categories/products/branches.
- If we later add menu write operations, Foodics requires `menu.write` for create/update/delete on menu resources.
