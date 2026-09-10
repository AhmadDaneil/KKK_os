# KKK OS V1 — Stage 5 Balance Payment Foundation

## Scope
This stage adds a provider-agnostic balance payment foundation.

It does NOT choose or integrate the real production payment gateway yet.

## Tables
- payment_transactions
- payment_events

## Core flow
DESIGN_APPROVED
→ create BALANCE payment
→ order becomes BALANCE_PENDING
→ verified successful callback
→ payment becomes PAID
→ order becomes PAID

## Important
- Duplicate balance-payment creation returns the existing active payment.
- Duplicate callback events are idempotent.
- Database remains source of truth.
- Payment provider integration must implement PaymentGatewayContract later.
- Do not store card details.
- Real callback signature verification is mandatory when provider is known.

## Add relationships

App\Models\Order:

public function payments()
{
    return $this->hasMany(\App\Models\PaymentTransaction::class);
}

App\Models\PaymentTransaction is included in this package.

## Install
1. Merge app/, database/, tests/, docs/.
2. Add the Order relationship.
3. Add local dev routes.
4. Add local-only CSRF exceptions.
5. Run:
   php artisan migrate
   php artisan test
