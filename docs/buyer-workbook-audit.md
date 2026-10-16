# Buyer audit before implementation

Source: the supplied pasted Buyer requirements and `C:/Users/Yuan Gallardo/Downloads/Blackbox_Test_Cases_Account_Creation-1.xlsx`. The attachment folder contained the instructions; the workbook was subsequently found in Downloads. Summary, Test Data, Test Files, Assumptions, and Buyer sheets were inspected. Only the ten permitted specification columns were extracted from Buyer; Actual Result, Status, and Remarks were excluded. The classifications below come from inspecting the original code, before implementing the changes.

| Cases | Classification from current code | Finding |
| --- | --- | --- |
| 001 | Conflict with current business rule | Website registrations currently become active immediately. The requested new flow explicitly requires email OTP first. Existing active accounts must remain usable. |
| 002–003 | Partial | Server required fields exist; name, sex, DOB, confirmation inline errors and duplicate request UX need improvement. |
| 004–006 | Partial | Names allow arbitrary characters and up to 100; requested Unicode letters/punctuation and 2–50 boundaries missing. |
| 007–008 | Partial | Future dates rejected; 18+ boundary missing. |
| 009–012 | Partial | Email format and database unique email exist; lowercase persistence exists, but normalized case-insensitive validation including archived users needs improvement. |
| 013–016 | Partial | 09 / +63 normalization exists, but malformed symbols are stripped, bare 63 is accepted, and mobile uniqueness is missing. |
| 017–020 | Partial | Passwords hashed and confirmed; default strength rules do not enforce all five categories. Visibility toggles/checklist missing. |
| 021 | Partial | Accepted terms required; links are plain text. |
| 022 | Partial | Email unique index and transaction exist; normalized mobile database protection and submit guard missing. |
| 023–027 | Missing | No six-digit OTP challenge, expiry, attempts, resend, one-time invalidation, or verification UI. |
| 028 | Missing | No unverified Buyer state or verification-only login. |
| 029 | Already implemented | Authenticated session, regeneration, remember-me, name, storefront/account navigation. Login throttling needs improvement. |
| 030 | Already implemented | Backend role middleware protects other role areas. Regression checks required. |

Existing SHOPPICK-specific fields: optional middle initial, required sex, structured Philippine address, and required private valid-ID upload. Preserve these features. ID upload is an existing requirement outside the supplied workbook scenarios; it does not justify adding administrative Buyer approval.

The address component already has dependent Region → Province → City/Municipality → Barangay, retry/fallback handling, and server geography checks. Postal-code length is currently loose and needs exactly four digits for Buyer website forms.

Mobile endpoints use their own registration validator and BuyerAccountState. Keep that existing immediate activation contract and API payloads unchanged; use a dedicated website request and OTP state. Google email verification remains a trusted existing flow. New website unverified accounts must stay blocked in mobile by their inactive state until verified.

No live account changes, destructive database commands, git staging, commits, or pushes are authorized by this task.
