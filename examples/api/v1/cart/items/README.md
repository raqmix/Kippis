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

## Examples

### 1. Simple Product (no addons)
**File:** `01-simple-product.json`

Adds a simple product to the cart without any addons or modifiers.

```json
{
  "item_type": "product",
  "product_id": 1,
  "quantity": 2
}
```

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

1. **Price Calculation**: Price is computed ONCE when adding and stored in `cart_item.price`. Cart totals are calculated by summing stored item prices (no repricing after save).

2. **Modifier Levels**: Level must be between 0 and `modifier.max_level`. Level 0 means no modifier applied. Price calculation: `modifier.price * level`

3. **Backward Compatibility**: If `item_type` is not provided, the old format is used: `{"product_id":1,"quantity":2}`

4. **Product Details**: Use `include_product=true` query parameter to include full product details with `allowed_addons` in the response.

5. **Validation**: All fields are validated according to the `AddMixToCartRequest` validation rules.

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

