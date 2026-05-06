# Session Notes — Lalamove Integration

## What was done

### 1. New test files created

**`tests/Fixtures/LalamoveOrder.php`**
Order fixture for the MY market. Uses:
- Pickup: Suria KLCC (lat 3.1578, lng 101.7118)
- Delivery: Bangsar Shopping Centre (lat 3.1306, lng 101.6838)
- Service type: `MOTORCYCLE`, language: `en_MY`
- Sender/recipient with MY phone numbers, POD enabled
- `metadata.referenceId` set to the order number (used for webhook correlation)

Data shape matches what `LalamoveDriver::createConsignment()` expects.

**`tests/Unit/LalamoveTest.php`** — unit tests, no API credentials needed
- Driver resolution
- `redirectTrack` (string and array input)
- `getConsignmentSlip` / `getConsignmentableSlip` / `getConsignmentableSlips` throw `BadMethodCallException` (Lalamove has no waybill labels)
- `pushShipmentStatus` webhook handling
- Metadata `referenceId` → order number resolution
- Fallback to `orderId` when metadata is empty
- Data-driven status normalisation for all 8 statuses (ASSIGNING_DRIVER, ON_GOING, PICKED_UP, COMPLETED, CANCELED, REJECTED, EXPIRED, unknown)

**`tests/Unit/LalamoveApiTest.php`** — integration tests, require live API credentials
- `test_it_can_create_consignment`
- `test_it_can_create_consignment_with_slip`
- `test_it_can_create_consignment_with_special_requests` (uses `TOLL_FEE_10`)
- `test_it_can_get_consignment_details`
- `test_it_can_get_last_shipment_for_multiple_orders`
- `test_it_can_cancel_consignment`

### 2. Reference: Postman collection used
`/mnt/e/Downloads/MY.postman_v3.4collection.json` — Lalamove MY market v3 API collection.

Endpoints covered by the collection:
| Name | Method | Path |
|---|---|---|
| Get Quotation | POST | /v3/quotations |
| Place Order | POST | /v3/orders |
| Add Priority Fee | POST | /v3/orders/{id}/priority-fee |
| Get Order Details | GET | /v3/orders/{id} |
| Edit Order | PATCH | /v3/orders/{id} |
| Get Quotation Details | GET | /v3/quotations/{id} |
| Get City Info | GET | /v3/cities |
| Get Driver Details | GET | /v3/orders/{id}/drivers/{driverId} |
| Cancel Order | DELETE | /v3/orders/{id} |
| Change Driver | DELETE | /v3/orders/{id}/drivers/{driverId} |
| Webhook | PATCH | /v3/webhook |

### 3. Webhook explained

See [lalamove-webhooks.md](../lalamove/lalamove-webhooks.md) for the full webhook guide, including payload shape, Laravel controller setup, CSRF exemption, status map, and local development tunnelling.

## .env keys required

```
LALAMOVE_TEST_MODE=true
LALAMOVE_API_KEY=
LALAMOVE_API_SECRET=
LALAMOVE_MARKET=MY
LALAMOVE_ORDER_NUMBER_KEY=referenceId
```

## Status map

| Lalamove status | ShipmentStatus |
|---|---|
| ASSIGNING_DRIVER | Pending |
| ON_GOING | Accepted |
| PICKED_UP | Pickup |
| COMPLETED | Delivered |
| CANCELED | ReturnStart |
| REJECTED | DeliveryAttempted |
| EXPIRED | OnHold |
| (anything else) | Unknown |

## Run tests

```bash
# Unit tests only (no credentials needed)
./vendor/bin/phpunit tests/Unit/LalamoveTest.php

# API integration tests (needs .env credentials)
./vendor/bin/phpunit tests/Unit/LalamoveApiTest.php
```
