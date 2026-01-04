# POST api/v1/cart/items - Request Examples

This directory contains example JSON request bodies for the `POST api/v1/cart/items` endpoint.

## Endpoint
```
POST /api/v1/cart/items
```

## Headers
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token} (optional, for authenticated users)
```

## Query Parameters
- `include_product` (boolean, optional): Include full product details in response. Example: `?include_product=true`

## Body Parameters (Additional)
- `store_id` (integer, optional): Store ID for the cart. If not provided, uses the first active store. Example: `1`

## Examples

### 1. Simple Product (no addons)
**File:** `01-simple-product.json`

Adds a simple product to the cart without any addons or modifiers. Includes `store_id` to specify which store.

```json
{
  "item_type": "product",
  "product_id": 1,
  "quantity": 2,
  "store_id": 1
}
```

**Note:** If no cart exists, a new cart is automatically created for the session. If `store_id` is omitted, the first active store is used.

**cURL Example:**
```bash
curl -X POST "https://your-domain.com/api/v1/cart/items" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @examples/api/v1/cart/items/01-simple-product.json
```

---

### 2. Product with Addons
**File:** `02-product-with-addons.json`

Adds a product with modifier addons and a custom note.

```json
{
  "item_type": "product",
  "product_id": 1,
  "quantity": 1,
  "addons": [
    {
      "modifier_id": 5,
      "level": 2
    },
    {
      "modifier_id": 8,
      "level": 1
    }
  ],
  "note": "Extra hot please"
}
```

**Fields:**
- `addons`: Array of modifier configurations
  - `modifier_id`: ID of the modifier/addon (required)
  - `level`: Modifier level (0 to max_level, optional, default: 1)
- `note`: Custom note for this item (optional, max 1000 characters)

---

### 3. Custom Mix
**File:** `03-custom-mix.json`

Adds a custom mix with base product, modifiers, and extras.

```json
{
  "item_type": "mix",
  "quantity": 1,
  "configuration": {
    "base_id": 1,
    "modifiers": [
      {
        "id": 2,
        "level": 3
      },
      {
        "id": 5,
        "level": 1
      }
    ],
    "extras": [3, 4]
  },
  "name": "My Custom Mix"
}
```

**Fields:**
- `item_type`: "mix" (required)
- `configuration`: Mix configuration object (required)
  - `base_id`: Base product ID with product_kind = mix_base (preferred)
  - `modifiers`: Array of modifier configurations (optional)
    - `id`: Modifier ID (required in modifier object)
    - `level`: Modifier level 0-max_level (optional, default: 1)
  - `extras`: Array of extra product IDs (optional)
- `name`: Custom name for the mix (optional)

---

### 4. Creator Mix
**File:** `04-creator-mix.json`

Adds a creator mix with reference ID.

```json
{
  "item_type": "creator_mix",
  "quantity": 1,
  "configuration": {
    "base_id": 1,
    "modifiers": [
      {
        "id": 2,
        "level": 2
      }
    ],
    "extras": []
  },
  "ref_id": 10,
  "name": "Berry Blast Mix"
}
```

**Fields:**
- `item_type`: "creator_mix" (required)
- `ref_id`: Reference ID (mix_builder_id or creator_mix_id) (optional)
- `name`: Custom name for the mix (optional)

---

### 5. Legacy Format (Backward Compatibility)
**File:** `05-legacy-format.json`

Legacy format for backward compatibility. Used when `item_type` is not provided.

```json
{
  "product_id": 1,
  "quantity": 2,
  "modifiers": [1, 2, 3],
  "note": "No ice"
}
```

**Note:** The `modifiers` field accepts an array of modifier IDs (not objects with level).

---

### 6. Product with Note Only
**File:** `06-product-with-note.json`

Adds a product with just a custom note, no addons.

```json
{
  "item_type": "product",
  "product_id": 1,
  "quantity": 1,
  "note": "Please make it extra hot"
}
```

---

### 7. Mix with Base Price and Builder ID
**File:** `07-mix-with-base-price.json`

Adds a mix with base price (deprecated field) and builder ID for validation.

```json
{
  "item_type": "mix",
  "quantity": 2,
  "configuration": {
    "base_id": 1,
    "base_price": 15.00,
    "builder_id": 1,
    "modifiers": [
      {
        "id": 2,
        "level": 1
      }
    ],
    "extras": [3]
  },
  "name": "Special Mix",
  "note": "Extra shot please"
}
```

**Fields:**
- `configuration.base_price`: Deprecated. Raw base price for backward compatibility
- `configuration.builder_id`: Mix builder ID to validate base belongs to builder
- `configuration.mix_builder_id`: Alias for builder_id

---

### 8. Auto-Create Cart (No store_id)
**File:** `08-auto-create-cart.json`

Adds a product without specifying `store_id`. The API will automatically create a cart using the first active store that receives online orders.

```json
{
  "item_type": "product",
  "product_id": 1,
  "quantity": 1
}
```

**Fields:**
- `store_id`: Omitted - API uses first active store automatically

**When to use:** When you don't need to specify a specific store, let the API choose the default active store. The API will automatically create/open a session cart if not found.

---

## Response Examples

### Success Response (201)
```json
{
  "success": true,
  "message": "item_added",
  "data": {
    "id": 123,
    "items": [
      {
        "id": 1,
        "item_type": "product",
        "name": "Product Name",
        "quantity": 2,
        "price": 25.50,
        "addons": [{"modifier_id": 5, "level": 2}]
      }
    ],
    "subtotal": 25.50,
    "discount": 0.00,
    "total": 25.50
  }
}
```

### Error Responses

**400 - Product Inactive:**
```json
{
  "success": false,
  "error": "PRODUCT_INACTIVE",
  "message": "product_inactive"
}
```

**400 - Invalid Configuration:**
```json
{
  "success": false,
  "error": "INVALID_CONFIGURATION",
  "message": "Modifier level 5 exceeds maximum level 3"
}
```

**404 - Cart Not Found:**
```json
{
  "success": false,
  "error": "CART_NOT_FOUND",
  "message": "cart_not_found"
}
```

---

## Important Notes

1. **Auto Cart Creation**: The API automatically creates a new session cart if no active cart is found. You don't need to call `/api/v1/cart/init` before adding items. The cart is created automatically using:
   - The `store_id` from the request body (optional), or
   - The first active store that receives online orders (if `store_id` is not provided)

2. **Session Cart**: For guest users (not authenticated), a session-based cart is automatically created using the session ID. The same session ID will reuse the same cart.

3. **Price Calculation**: Price is computed ONCE when adding and stored in `cart_item.price`. Cart totals are calculated by summing stored item prices (no repricing after save).

4. **Modifier Levels**: Level must be between 0 and `modifier.max_level`. Level 0 means no modifier applied. Price calculation: `modifier.price * level`

5. **Backward Compatibility**: If `item_type` is not provided, the old format is used: `{"product_id":1,"quantity":2}`

6. **Product Details**: Use `include_product=true` query parameter to include full product details with `allowed_addons` in the response.

7. **Validation**: All fields are validated according to the `AddMixToCartRequest` validation rules.

8. **Store ID**: The `store_id` field is optional. If not provided, the system uses the first active store that receives online orders.

---

## Testing with cURL

You can test these examples using cURL:

```bash
# Simple product
curl -X POST "http://localhost/api/v1/cart/items" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @examples/api/v1/cart/items/01-simple-product.json

# Product with addons
curl -X POST "http://localhost/api/v1/cart/items" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @examples/api/v1/cart/items/02-product-with-addons.json

# Custom mix
curl -X POST "http://localhost/api/v1/cart/items" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @examples/api/v1/cart/items/03-custom-mix.json
```

## Testing with Postman

1. Import the examples into Postman
2. Set the base URL to your API endpoint
3. Add the `Content-Type: application/json` header
4. Copy the JSON from any example file into the request body
5. Send the request

