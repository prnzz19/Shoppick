# Rider account lifecycle

Riders are created by an authenticated, active Logistics user whose registration is approved (or a legacy active Logistics user with no registration metadata). There is no public Rider self-registration.

## Create and activate

The Logistics Rider form collects the Rider's name, date of birth, email, mobile, license number and expiry, vehicle type, plate number, Driver's License upload, and vehicle OR/CR upload. Email is trimmed/lowercased and unique across all roles. The authenticated Logistics user becomes the Rider's owner; a submitted owner ID is ignored. New accounts receive only the `rider` role and start inactive with `pending_activation` status.

SHOPPICK stores only a SHA-256 hash of a cryptographically random activation token, with a 24-hour expiration. The email includes the Rider and provider names, password setup link, expiration, and a security reminder. Activation applies the shared password rule (8+ characters, uppercase, lowercase, number, special character, and matching confirmation), activates the User and RiderProfile, and consumes the token in one transaction. Invalid, expired, rotated, or used links cannot set a password.

Resending is available only to the owning Logistics account and only while the Rider remains pending activation. Rotating the single token record invalidates the previous link. If mail delivery fails, the Rider remains pending and the Logistics user can retry; UI and logs do not include SMTP errors or token values.

## Ownership and legacy records

`rider_profiles.logistics_user_id` links each newly created Rider to the creating Logistics user. It is nullable to preserve historical rows whose owner is unknown. No legacy Rider is assigned to a provider by guesswork; unowned rows are excluded from ordinary Logistics Rider lists, details, management, document downloads, and assignment choices. Admin monitoring remains separate.

The Rider's Logistics Provider display is derived from the owning user's current name. Rider assignment choices and service-level assignment checks require an active, eligible Rider owned by the acting approved Logistics user. Pending-activation Riders cannot be assigned.

## Routes

- `GET/POST /rider/activate/{token}`: password setup and activation.
- `/rider/login`: Rider sign-in; pending activation receives a clear denial and cannot enter the portal.
- `/rider/register` and `/register/rider`: legacy bookmarks redirect to Rider Login with the provider-created account message; their POST endpoints return `410 Gone`.
- Logistics Rider management endpoints require authentication, the `manage_riders` permission, and approved Logistics status. Rider-bound operations verify ownership server-side and return `404` for another provider's Rider.

Older application-status records are preserved. They are not included in Logistics provider lists unless the record has a known owner. New Rider accounts do not require Buyer registration, Buyer role, Buyer OTP, or separate Admin approval.

## Workbook coverage

`tests/Feature/RiderWorkbookLifecycleTest.php` covers TC-RID-001 and TC-RID-019–032, and assumptions A3, A5, A12, A13, and A16. Related compatibility tests cover preservation of existing Rider portal access, legacy unassigned records, and private document access.
