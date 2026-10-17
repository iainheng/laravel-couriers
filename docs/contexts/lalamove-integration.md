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
LALAMOVE_CITY=KUL          # city deliveries start from, for serviceTypes()
```

## Status map

| Lalamove status | ShipmentStatus |
|---|---|
| ASSIGNING_DRIVER | Pending |
| ON_GOING | Accepted |
| PICKED_UP | Pickup |
| COMPLETED | Delivered |
| CANCELED | Cancelled |
| REJECTED | DeliveryAttempted |
| EXPIRED | Expired |
| (anything else) | Unknown |

## Run tests

```bash
# Unit tests only (no credentials needed)
./vendor/bin/phpunit tests/Unit/LalamoveTest.php

# API integration tests (needs .env credentials)
./vendor/bin/phpunit tests/Unit/LalamoveApiTest.php
```

## Quotes before booking and the charged price

- Quoting is an optional capability: drivers whose courier has a quotation API implement `Nextbyte\Courier\Contracts\Quotable` (`quote(array $attributes): Quotation`). Only `LalamoveDriver` does today, so apps should check `$driver instanceof Quotable` before calling `quote()`.
- `Nextbyte\Courier\Quotation` is the generic result (`id`, `expiresAt`, `price`, `serviceType`, `distanceMeters`, `isExpired()`, `toArray()` / `fromArray()`); `LalamoveQuotation` extends it with `stopIds`. Lalamove refuses to book with a quotation that is not a `LalamoveQuotation`.

- `LalamoveDriver::quote(array $attributes): LalamoveQuotation` gets a price without booking. It takes the same attributes as `createConsignment()`.
- `LalamoveQuotation` holds the quotation ID, stop IDs, `expiresAt` (about 5 minutes), `price` (`Nextbyte\Courier\Price`: `total`, `currency`, `breakdown`), `serviceType` and `distanceMeters`. `toArray()` / `fromArray()` let an app keep it between requests (e.g. in the cache).
- Pass it back as the `quotation` attribute (object or array) to `createConsignment()` / `createConsignmentWithSlip()` to book at exactly the quoted price. An expired quotation throws `CourierException` before any API call; without a `quotation`, the driver quotes and books in one go as before.
- `createConsignmentWithSlip()` sets `Consignment::$price` to what Lalamove charged (from the Place Order response's `priceBreakdown`).
- Unit tests: `test_it_can_quote_without_booking`, `test_create_consignment_books_with_the_given_quotation`, `test_an_expired_quotation_is_refused_without_calling_lalamove`, `test_create_consignment_with_slip_reports_the_charged_price`, and others in `LalamoveTest`.

## Cancelling

- `LalamoveDriver::cancelConsignmentOrFail($orderId)` cancels and throws `CourierException` with Lalamove's reason; `cancelConsignment()` keeps returning `false` instead.
- Sandbox, 17 Oct 2026: cancelling while `ASSIGNING_DRIVER` works; cancelling again answers `ERR_CANCELLATION: Cannot cancel order.` Lalamove's docs allow cancelling up to about 5 minutes after a driver is matched.
- Edit Order (`PATCH /v3/orders/{id}`) answered `ERR_ORDER_EDIT: Cannot edit order` for every edit tried (removing a drop-off, or only changing a remark) on a fresh order. It appears to need enabling for the account, so removing a single stop is not supported.

## Cancelling with any courier (`Contracts\Cancellable`)

- Drivers whose courier API can cancel a booking implement `Nextbyte\Courier\Contracts\Cancellable` (`cancelConsignmentOrFail(string $reference): void`, throwing `CourierException` with the courier's reason). Check `$driver instanceof Cancellable` first; other drivers keep throwing from `cancelConsignment()`.
- **Lalamove** cancels by its orderId (the consignment/tracking number).
- **DHL eCommerce** cancels by the **shipmentID** the label was created with, not its tracking number (`deliveryConfirmationNo`): `DhlEcommerce::deleteShipment()` calls `POST /rest/v2/Label/Delete` (`deleteShipmentReq`, messageType `DELETESHIPMENT`). DHL allows it for domestic shipments before pick-up/processing, and for international ones before close-out ([docs](https://api.dhlecommerce.dhl.com/API/docs/v2/deleteshipment.html)).
- Not yet verified against DHL: its test environment (`apitest.dhlecommerce.asia`) answered 502 for every request on 17 Oct 2026, so `DeleteShipmentResponse` follows the documented field tables. Also unverified: whether a deleted shipmentID can be labelled again (DHL rejects re-used IDs when creating labels).

## Route optimisation and stop order

- With `isRouteOptimized`, Lalamove returns the quotation's stops **in driving order**, not request order (sandbox, 17 Oct 2026: requested far, near, middle came back near, middle, far; RM 38.50 instead of RM 67.80). The Place Order and Get Order responses list stops in driving order too, without stopIds or remarks.
- Before v1.11.0 the driver took stopIds in response order and gave them to recipients by request position, so a reordered multi-drop booking attached recipients' names, phones and remarks to other recipients' addresses.
- `LalamoveQuotation::fromResponse($response, $requestedStops)` now matches returned stops to requested ones by coordinates (Lalamove rounds to 7 decimals) or address, puts `stopIds` back in request order, and records `routeSequence` (requested indexes in driving order). `dropOffNumber($i)` gives a requested drop-off's position in the route. Unmatched stops throw before anything is booked. Verified in the sandbox.
- `Consignment::$trackingUrl` carries Lalamove's `shareLink` (live tracking page).

## Vehicles and their limits (`Contracts\ProvidesServiceTypes`)

`serviceTypes(?string $city = null)` lists the vehicles Lalamove offers in a city, from its City Info
API (`GET /v3/cities`), as `ServiceType` objects: `key` (send it as `serviceType`), Lalamove's
`description`, and the largest load normalised to `maxWeightKg` and `lengthCm` / `widthCm` / `heightCm`.

The city is Lalamove's UN/LOCODE with or without the market (`KUL` or `MY KUL`). It defaults to
`LALAMOVE_CITY`; a market with a single city needs none. An unknown or missing city throws a
`CourierException` listing the cities Lalamove serves.

Kuala Lumpur (sandbox, Oct 2026): MOTORCYCLE 10 kg 30×30×30 cm · CAR 40 kg 50×50×50 cm ·
4X4 250 kg · VAN 500 kg · TRUCK330 1,000 kg · TRUCK550 2,500 kg. Other cities add LARGEVAN.
The list changes by city and over time, so fetch it rather than hard-coding it; it rarely changes,
so cache it (a day is plenty).

## Each recipient at their own address

Lalamove's stop IDs belong to addresses. `LalamoveQuotation` keeps the stops it was quoted for, and
`forStops()` lines a quotation up with a booking that lists the same stops in another order, so each
recipient gets the stop ID of their own address (two stops at one building are told apart by address).

After booking, `placeOrder()` checks the stops Lalamove returns (or fetches the order when the response
has none): every recipient's name must be at the coordinates they were booked for. If not, the order
is cancelled and a `CourierException` names who went where; if it cannot be cancelled, the message
says to cancel it in Lalamove now. Names are compared ignoring case, spacing and punctuation, and a
name Lalamove shortened still matches. When Lalamove does not say who is at each stop, the booking
stands and a warning is logged.

`LalamoveQuoteStopOrderTest` books every quote order × booking order × several route orders;
`LalamoveRecipientCheckTest` covers the after-booking check.
