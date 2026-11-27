<section id="order-add-ons" class="staff-section">
    <h2 class="staff-section-title">Add-On</h2>
    <div class="staff-card">
        <p>Select an item and quantity. Its price will be added to the existing order invoice and remaining balance.</p>
        @if ($addOnBlockingReason === null)
            <form method="POST" action="{{ route($operationRoutePrefix.'orders.add-ons.store', $order) }}" class="staff-add-on-form" data-staff-add-on-form>
                @csrf
                <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                <label for="staff-add-on-code">Add-On item</label>
                <select id="staff-add-on-code" name="code" required>
                    <option value="">Choose an Add-On</option>
                    @foreach ($staffAddOnOptions as $option)
                        <option value="{{ $option['code'] }}" data-unit-cents="{{ $option['unit_price_cents'] }}" data-min-quantity="{{ $option['min_quantity'] }}" @selected(old('code') === $option['code'])>{{ $option['option_label'] }}</option>
                    @endforeach
                </select>
                <label for="staff-add-on-quantity">Quantity</label>
                <input id="staff-add-on-quantity" type="number" name="quantity" min="1" max="100000" step="1" value="{{ old('quantity', 1) }}" required>
                <label for="staff-add-on-notes">Colour / customisation notes (optional)</label>
                <input id="staff-add-on-notes" name="notes" maxlength="80" value="{{ old('notes') }}" placeholder="Example: Rosegold, couple names or stamp details">
                <p>Welcome Board postage: RM15 per board, included in the displayed unit total. Button Badge minimum: 10 pcs.</p>
                <p role="status" aria-live="polite" data-staff-add-on-total>Total: RM 0.00</p>
                <button class="staff-button" type="submit">Add Add-On</button>
            </form>
        @else
            <p class="staff-muted-text">{{ $addOnBlockingReason }}</p>
        @endif
        <h3>Order Add-Ons</h3>
        @forelse ($staffOrderAddOns as $item)
            <div class="staff-packing-item">
                <div><strong>{{ $item->description }}</strong><p>{{ $item->quantity }} × RM {{ number_format((float) $item->unit_price, 2) }}</p></div>
                <strong>RM {{ number_format((float) $item->line_total, 2) }}</strong>
            </div>
        @empty
            <p>No Add-Ons added yet.</p>
        @endforelse
    </div>
</section>
<script>
(() => {
    const form = document.querySelector('[data-staff-add-on-form]');
    if (!form) return;
    const select = form.querySelector('[name="code"]');
    const quantity = form.querySelector('[name="quantity"]');
    const total = form.querySelector('[data-staff-add-on-total]');
    const update = (changedItem) => {
        const option = select.selectedOptions[0];
        const minimum = Number(option?.dataset.minQuantity || 1);
        quantity.min = String(minimum);
        if (changedItem && Number(quantity.value) < minimum) quantity.value = String(minimum);
        const cents = Number(option?.dataset.unitCents || 0) * Math.max(0, Number(quantity.value) || 0);
        total.textContent = 'Total: RM ' + (cents / 100).toFixed(2);
    };
    select.addEventListener('change', () => update(true));
    quantity.addEventListener('input', () => update(false));
    update(false);
})();
</script>
