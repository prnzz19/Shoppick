# Admin Logistics monitoring

Admin reads the existing SHOPPICK logistics records on every request. There is no synchronization job, copied status, Admin logistics table, new schema, or second database.

## Shared records

- Shipment / shipments: tracking number, parcel code, current status, rider foreign keys, timestamps.
- Order / orders and SellerOrder / seller_orders: existing buyer order and seller fulfillment references.
- Store / stores and User / users: shop, seller, buyer, logistics staff, and riders.
- Role / roles and role_user: existing account roles.
- RiderProfile / rider_profiles: current account status and availability.
- ShipmentEvent / shipment_events: the existing chronological delivery timeline and actor-based rider activity.
- ProofOfDelivery / proof_of_deliveries: existing rider-submitted proof; served from private local storage through an Admin-authenticated GET route.
- LogisticsHub / logistics_hubs: current facility.

Shipment.order, sellerOrder, store, rider, pickupRider, events, proofOfDelivery, and currentHub are existing model relationships. Store.user supplies the seller. No model or operational service changes were needed. No provider model/field exists, so provider is displayed as Not recorded.

## Routes and authorization

Existing LogisticsMonitoringController handles read-only Admin queries, not operational Logistics controller methods.

GET /admin/logistics redirects to the existing overview.
GET /admin/logistics/overview
GET /admin/logistics/deliveries
GET /admin/logistics/deliveries/{shipment}
GET /admin/logistics/deliveries/{shipment}/proof
GET /admin/logistics/riders
GET /admin/logistics/riders/{rider}

All inherit auth and role:admin middleware. The proof route resolves the proof from the selected shipment and returns 404 for absent files. Proof responses use private/no-store caching. No Admin mutation endpoints, proof uploads, approvals, assignments, or availability controls were added. Logistics and Rider middleware and operational routes are unchanged.

## Counts and activity

Status summaries group shipments by their stored status, without mapping to separate Admin statuses.
Active Shipments excludes delivered, completed, returned, delivery_failed, and exception.
Out for Delivery counts out_for_delivery.
Delivered includes delivered and buyer-confirmed completed.
Failed / Returned counts delivery_failed and returned.
Rider active counts include delivery assignments and pickups before sorting handover, counting a shipment only once.
Completed rider deliveries use the delivery rider_id and delivered/completed statuses.
Last Activity is the latest shipment event actually performed by the rider; absence is shown explicitly.
Recent Logistics Activity uses shipment updated_at. Rider activity lists actual shipment_events, including their notes and timestamps.
Refreshing Admin runs fresh database queries; there is no polling or copied state.

## Verification

106 tests passed, 1677 assertions, with SQLite :memory: and array mail.
The new HTTP integration test performs pickup assignment, pickup acceptance/collection, sorting-center receipt/scan/sorting, delivery assignment, rider acceptance/start, proof upload, COD collection, and delivery completion through the existing Logistics/Rider routes.
After transitions, Admin is checked against the same shipment ID, current status, rider, updated_at, and exact event IDs. Shipment count remains unchanged. Logistics and Buyer tracking show out_for_delivery. Admin can view the same proof and missing files return 404.
Authorization tests reject non-admin monitoring access and Admin-only operational requests.
npm.cmd run build and php artisan optimize:clear passed.
Read-only MySQL previews rendered all five pages; shipment row hashes were unchanged. No live workflow transitions were performed.

## Files changed for this update

- app/Http/Controllers/Admin/LogisticsMonitoringController.php
- routes/admin.php
- resources/views/admin/logistics/overview.blade.php
- resources/views/admin/logistics/delivery-table.blade.php
- resources/views/admin/logistics/delivery.blade.php
- resources/views/admin/logistics/riders.blade.php
- resources/views/admin/logistics/rider.blade.php
- tests/Feature/AdminLogisticsMonitoringTest.php
- documentation/admin-logistics-monitoring.md

Frontend build outputs were regenerated. Existing sidebar, deliveries search/filter page, account management, Logistics/Rider dashboards, database structure, and operational services were preserved.