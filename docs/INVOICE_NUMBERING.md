# Invoice number prefixes

New invoices use the phone number of the business line that handled the customer. That service phone is saved on the order when it is created, then determines the prefix while keeping the date and daily sequence from the customer reference:

| Brand | Invoice prefix | Example |
| --- | --- | --- |
| `+60 11-6565 6968` — KingKadKahwin | `KKK` | `KKK261019-001` |
| `+60 17-719 7596` — Destinasi Raja Sehari | `DRS` | `DRS261019-001` |
| `+60 11-3562 3239` — Royalicious | `RYL` | `RYL261019-001` |
| `+60 17-586 3239` — Cetaking | `CK` | `CK261019-001` |

Customer references and order IDs are unchanged. Existing issued invoice numbers are retained when an invoice is refreshed; the phone-based prefix mapping applies only when an invoice is first created. Historical orders without a saved service phone use their existing Wabot channel (or the KingKadKahwin line for direct orders) to resolve the configured business line.
