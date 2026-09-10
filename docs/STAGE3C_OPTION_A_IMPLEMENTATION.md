# KKK OS V1 — Stage 3C Option A

Business decision locked by Project Owner:

> `qtykad` is one card quantity per business order.

## Approved ownership

Database source of truth:

`orders.card_quantity`

Do not duplicate the authoritative quantity on `order_package_sides`.

For a 2-package order:
- LELAKI merge job inherits `orders.card_quantity`.
- PEREMPUAN merge job inherits the same `orders.card_quantity`.

## Files in this patch

- migration adding `orders.card_quantity`;
- DB-backed `CardQuantityProviderContract` implementation;
- production provider binding;
- canonical merge payload adds `source.card_quantity`;
- regression test proving both 2-package rows use the same quantity.

## Required small edits to existing customer-data files

These are intentionally documented instead of replacing whole Stage 2 files, so earlier
bug fixes are not overwritten.

### 1. app/Models/Order.php

Add `card_quantity` to `$fillable`.

Add to `casts()`:

```php
'card_quantity' => 'integer',
```

### 2. CustomerOrderDraftController validation

Add:

```php
'card_quantity' => ['nullable', 'integer', 'min:1'],
```

It is order-level, not inside `sides`.

### 3. SaveOrderDraftService

Inside the transaction, before side processing, add:

```php
if (array_key_exists('card_quantity', $data)) {
    $order->forceFill([
        'card_quantity' => $data['card_quantity'] === null
            ? null
            : (int) $data['card_quantity'],
    ])->save();
}
```

Do not clear `card_quantity` when the field is absent from a partial draft request.

### 4. ValidateOrderCompletionService

Add one required-data check:

```php
if (! $order->card_quantity || (int) $order->card_quantity < 1) {
    $missing[] = 'Kuantiti kad';
}
```

This makes quantity mandatory before Final Confirmation / merge generation.

### 5. BuildFinalReviewService

At the order level add:

```php
'card_quantity' => $order->card_quantity,
```

The customer must see the cleaned/approved quantity before final confirmation.

### 6. Confirmation snapshot

Where the current project builds `confirmed_snapshot`, include:

```php
'card_quantity' => $order->card_quantity,
```

Do not remove any existing snapshot fields.

### 7. Customer Dashboard Blade

Add one order-level input, outside the per-side loop:

```blade
<fieldset>
    <legend>Kuantiti Kad</legend>

    <label for="card_quantity">Jumlah Kad</label>
    <input
        id="card_quantity"
        type="number"
        min="1"
        step="1"
        name="card_quantity"
        value="{{ old('card_quantity', $order->card_quantity) }}"
    >
</fieldset>
```

Do not create separate LELAKI/PEREMPUAN quantity inputs.

## Migration and tests

Run:

```powershell
php artisan migrate
php artisan optimize:clear
php artisan test --filter=CardQuantityOptionATest
php artisan test --filter=PhotoshopProductionExportWiringTest
php artisan test
```

## Important expected change to existing tests

Once `ValidateOrderCompletionService` requires `card_quantity`, fixtures that create a
complete/confirmable order must now provide a quantity. Add for example:

```php
$order->forceFill(['card_quantity' => 200])->save();
```

or include `card_quantity` through `SaveOrderDraftService`.

Tests intended to remain incomplete do not need this field.

## Stage 3C completion criteria

Stage 3C can be marked DONE only when:

- migration succeeds;
- Customer Dashboard saves one order-level quantity;
- Final Review displays it;
- confirmation snapshot contains it;
- canonical merge jobs contain `source.card_quantity`;
- DB-backed provider exports qtykad automatically;
- 1-package path passes;
- 2-package path passes and both rows carry the same qtykad;
- full regression suite passes.
