# Cetaking Production Booking Webhook Runbook

## Scope

This integration adds a Cetaking-specific Wabot webhook while reusing the shared
Billplz account and collection already configured for the application. The
webhook creates a Cetaking booking, creates or reuses its pending Billplz bill,
and sends the production payment URL to the customer through the Cetaking Wabot
sender.

The endpoints are:

```text
POST https://order.kingkadkahwin.com/api/v1/integrations/wabot/cetaking/booking/{sandbox-token}
POST https://order.kingkadkahwin.com/api/v1/integrations/wabot/cetaking/production-booking/{token}
```

The sandbox endpoint accepts only the phone number configured in
`WABOT_CK_BOOKING_TEST_PHONE` and only when the server uses the Billplz Sandbox
base URL. Its token is `WABOT_CK_BOOKING_WEBHOOK_TOKEN`. The production endpoint
uses a separate 64-character lowercase hexadecimal token in
`WABOT_CK_PRODUCTION_BOOKING_WEBHOOK_TOKEN`. Do not reuse either Royalicious
token or place a real token in this document, source control, or support chat.

## Required Wabot payload

```json
{
  "contact": {
    "id": "Wabot conversation identifier",
    "number": "60123456789"
  },
  "params": {
    "customer_name": "Customer full name",
    "customer_phone": "60123456789",
    "package_count": 1,
    "package_code": "PUTERI_MANIS",
    "payment_method": "ONLINE_BANKING"
  }
}
```

`contact.number` is preferred when it is a valid Malaysian mobile number. If
Wabot sends an `@bsuid` or another non-phone identifier, the webhook uses
`params.customer_phone`. The automation must collect and validate the customer's
phone before forwarding the webhook. Never derive the phone from the brand's
sender number.

Allowed package counts are 1 or 2. The shared booking pricing profiles currently
charge a RM50 deposit for one package and RM100 for two packages. Allowed
package codes are `PUTERI_MANIS` and `JELATA`.

`ONLINE_BANKING` uses the standard Billplz checkout. `DUITNOW_QR` is accepted
only when the shared `BILLPLZ_DUITNOW_QR_ENABLED=true` setting is enabled.

## Server configuration

The server must have the shared production Billplz settings and Cetaking's own
Wabot sender credentials configured:

- `BILLPLZ_ENABLED=true`
- `BILLPLZ_BASE_URL=https://www.billplz.com/api`
- `BILLPLZ_SECRET_KEY` and `BILLPLZ_COLLECTION_ID` from the approved shared
  production Billplz account
- `BILLPLZ_DUITNOW_QR_ENABLED=true` if Cetaking is to offer QR payments
- `WABOT_CK_INSTANCE_ID` and `WABOT_CK_ACCESS_TOKEN` for the Cetaking sender
- `WABOT_CK_BOOKING_WEBHOOK_TOKEN` and `WABOT_CK_BOOKING_TEST_PHONE` on staging
- `WABOT_CK_PRODUCTION_BOOKING_WEBHOOK_TOKEN` as a separate 64-character token

Never print or share secret values while checking configuration. Verify only
whether each setting is present. After updating Laravel environment settings,
refresh the application's configuration cache using the normal deployment
procedure.

## Wabot automation setup

Create a separate automation for the Cetaking account. Keep the working
Royalicious automation unchanged. Configure the chatbot to collect these inputs
before the webhook action runs:

- `customer_name` — required
- `customer_phone` — required; ask the customer for a Malaysian mobile number
  when Wabot's contact number is unavailable or is an `@bsuid`
- `package_count` — required, allowed values `1` and `2`
- `package_code` — required, allowed values `PUTERI_MANIS` and `JELATA`
- `payment_method` — required, allowed values `ONLINE_BANKING` and `DUITNOW_QR`

For staging, add one **Forward to Webhook** action with the sandbox endpoint and
the sandbox token. Keep the automation paused until staging is configured. A
staging call is restricted to the configured test phone and creates only a
Billplz Sandbox bill.

For production, change the action URL to the production endpoint and use its
separate production token only after staging passes. A live webhook call creates
a real Billplz production bill even if the customer does not pay it.

## Expected result and monitoring

On success, the endpoint returns HTTP 201 for a new booking or HTTP 200 when it
reuses the customer's active pending booking. The customer receives the Billplz
URL directly from Cetaking's Wabot sender. Application logs record the order ID
and delivery outcome without recording the token or full customer phone.

Do not interpret Wabot's generic "Completed" journey status as proof that a
payment URL was delivered. Confirm both the webhook result and the WhatsApp
message. For retries, the same Cetaking phone with a pending booking reuses the
existing order and bill.

## Release sequence

1. Review and merge the code change through the repository's normal PR process.
2. Configure Cetaking's Wabot credentials, sandbox token and test phone on
   staging; use Billplz Sandbox for staging.
3. Verify a complete staging chat, including the fallback `customer_phone`
   path, webhook response, and Wabot message delivery.
4. Confirm the shared Billplz production account, collection, RM50/RM100 pricing,
   and QR support with the account owner.
5. Configure production variables, deploy, and test only with explicit approval
   because the first live request creates a real Billplz bill.
6. Enable the Cetaking automation after the production checks pass.
