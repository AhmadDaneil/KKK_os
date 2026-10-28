# Royalicious Production Booking and Billplz Runbook

## Purpose and incident record

Royalicious bookings were rejected when sent to the sandbox webhook because that endpoint intentionally accepts only the phone number configured in `WABOT_RYC_BOOKING_TEST_PHONE`. Requests from other customers receive `403 TEST_PHONE_ONLY`; the Wabot automation displays a generic “Billplz link unavailable” response.

The production webhook is a separate endpoint. It accepts any valid Malaysian mobile number received in `contact.number`, creates or reuses that customer's active pending booking, creates or reuses the matching live Billplz bill, and sends the checkout link to that same number using the Royalicious Wabot sender. It has no single-test-phone allowlist.

The production endpoint must not be pointed at a staging or sandbox application. It refuses requests unless the application environment is `production` and the Billplz API base URL is the live Billplz host.

## Endpoint contract

Configure the Wabot **Forward to Webhook** action to send a POST request to:

```text
https://order.kingkadkahwin.com/api/v1/integrations/wabot/royalicious/production-booking/{WABOT_RYC_PRODUCTION_BOOKING_WEBHOOK_TOKEN}
```

The token is a random, lowercase 64-character hexadecimal value. Store it only in the production server's secret `.env` and the Wabot action URL. Do not put a real token in Git, a ticket, a screenshot, or a chat message. Use HTTPS and the production domain, not the VPS IP address.

The webhook expects this JSON structure from Wabot:

```json
{
  "contact": {
    "id": "60123456789@s.whatsapp.net",
    "number": "60123456789"
  },
  "params": {
    "customer_name": "Customer Full Name",
    "package_count": 1,
    "package_code": "PUTERI_MANIS",
    "payment_method": "ONLINE_BANKING"
  }
}
```

`contact.number` is the WhatsApp customer's number; `contact.id` is optional. Wabot must collect the full customer name, package count, package code, and payment method before forwarding the request. Supported package codes are `PUTERI_MANIS` and `JELATA`; package count must be `1` or `2`; payment method must be `ONLINE_BANKING` or `DUITNOW_QR`.

The endpoint sends the Billplz URL to `contact.number` itself. It does not depend on the AI reading a response field and forwarding the URL. Never use the brand sender number (`me_phone`) as the customer number.

## Production configuration checklist

Set these values only in the production secret store or production `.env`; never commit the values:

```text
APP_ENV=production
APP_URL=https://order.kingkadkahwin.com
BILLPLZ_ENABLED=true
BILLPLZ_BASE_URL=https://www.billplz.com/api
BILLPLZ_SECRET_KEY=<live Billplz API Secret Key>
BILLPLZ_X_SIGNATURE_KEY=<live Billplz X Signature Key>
BILLPLZ_COLLECTION_ID=<active live Collection ID>
BILLPLZ_DUITNOW_QR_ENABLED=false
WABOT_RYC_PRODUCTION_BOOKING_WEBHOOK_TOKEN=<new random 64-character lowercase hex token>
WABOT_BASE_URL=<verified HTTPS Wabot API base URL for this account>
WABOT_RYC_INSTANCE_ID=<Royalicious sender instance ID>
WABOT_RYC_ACCESS_TOKEN=<Royalicious sender access token>
```

Keep DuitNow QR disabled until the live Billplz account and collection have been approved for that method. Enable it only after a separate successful test. Billplz Sandbox credentials, collection IDs, and URLs are not interchangeable with production.

Before enabling the Wabot action, verify the live collection, deposit amount, callback URL, redirect URL, HTTPS certificate, and X Signature key. Billplz sends payment-status callbacks to the server; customer browser redirects alone are not proof of payment.

## Release and activation sequence

1. Merge the reviewed production-webhook change into `main` and deploy it to the production checkout.
2. Back up the production database and `.env` securely. Add the production values above without copying secrets into a terminal transcript or ticket.
3. Clear and rebuild Laravel's configuration cache. Confirm the `production-booking` route exists and that production-only prerequisites are present without printing their values.
4. In Wabot Royalicious → Automation → the booking automation → **Build Actions** → **Forward to Webhook**, replace the sandbox `/booking/{token}` URL with the production `/production-booking/{token}` URL. Keep the automation paused until the endpoint and credentials pass checks.
5. Review the four input mappings and contact variable. Ensure `contact.number` is supplied by Wabot and the four `params` fields match the endpoint contract exactly.
6. Enable the automation for a controlled pilot with a company-approved WhatsApp test customer. This creates a **real Billplz bill** and sends its link. Do not pay it unless the business owner explicitly approves a real transaction.
7. Confirm the production log records `Royalicious production payment link delivered through Wabot` and verify the bill appears in the correct live Billplz collection. Then test a second permitted customer number after the pilot is approved.

If any step fails, pause the Wabot automation and check the response code below and the structured Laravel log. Retrying the same customer's active pending booking reuses its existing bill and sends the same link; it should not create a second bill.

## Expected responses and troubleshooting

| Response | Meaning | Check |
| --- | --- | --- |
| `201`, `success: true` | New booking and live Billplz bill created; WhatsApp link sent | Verify order, Billplz collection, and recipient |
| `200`, `success: true` | Existing pending booking and bill reused; link sent again | Confirm the existing order belongs to this phone |
| `401 UNAUTHORIZED` | Production webhook token is missing or does not match | Compare the Wabot URL token and production secret without sharing either |
| `413 PAYLOAD_TOO_LARGE` | Wabot payload exceeds the endpoint limit | Remove unnecessary forwarded fields |
| `422` | Required input, Malaysian phone, or enabled payment method is invalid | Check the response's validation field names and mapping |
| `503 PRODUCTION_ONLY` | Request reached an application not running with `APP_ENV=production` | Confirm the target domain and deployed environment |
| `503 PRODUCTION_BILLPLZ_REQUIRED` | Billplz base URL is not the live API endpoint | Set `https://www.billplz.com/api`; never use the sandbox URL |
| `503 BILLPLZ_NOT_CONFIGURED` | A required Billplz live setting is missing | Check presence of enabled flag, live secret key, and collection ID without printing them |
| `503 HTTPS_APP_URL_REQUIRED` | Application URL does not use HTTPS | Set the production `APP_URL` and verify TLS |
| `503 WABOT_NOT_CONFIGURED` | Royalicious sender credentials or HTTPS API URL are missing | Check the Wabot API URL, instance ID, and access token |
| `409 BOOKING_CONFLICT` | The same active booking is still being prepared or conflicts with an existing one | Retry after a short wait; inspect the existing order/payment before manual intervention |
| `502 BILLPLZ_UNAVAILABLE` | Billplz did not create or confirm the live bill | Check Billplz status, credentials, collection, and Laravel payment log; do not create a replacement bill manually |
| `502 MESSAGE_DELIVERY_FAILED` | Bill exists, but Wabot did not accept the outgoing message | Retry the same webhook/booking or inspect Wabot delivery status; the existing pending bill should be reused |
| `409 NON_PRODUCTION_PAYMENT_URL` | Billplz returned a URL outside the allowed live host | Stop activation and investigate; do not send or pay that link |

Do not log full request bodies, tokens, access keys, customer phone numbers, or checkout URLs in public issue reports. Record the timestamp, order ID if available, response code, and provider HTTP status only.

## Verification record

The production webhook's automated tests should use HTTP fakes only. They must cover a non-test Malaysian customer number, one-bill retry behavior, production-environment guard, sandbox-host rejection, invalid token, malformed phone, and failed-message retry. These tests do not contact Billplz or send WhatsApp messages; a live pilot is still required to verify the actual Billplz account and Wabot credentials.
