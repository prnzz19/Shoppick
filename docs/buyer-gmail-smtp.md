# Gmail SMTP for local Buyer OTP

OTP email delivery uses Laravel's synchronous SMTP mailer; no queue worker is required. New Buyers remain Unverified until a correct current code is entered. Wrong/expired codes cannot activate them; resend replaces the old code after the cooldown. No Admin approval is required. Failed mail delivery retains the account and offers Resend.

The ignored local `.env` now selects Gmail SMTP with required TLS and a 30-second timeout. Existing username/password values were preserved. The sender expands from the username, so they use the same account. No real credentials were supplied by this task.

Replace placeholders below in `.env` only. Do not copy placeholders over existing valid credentials. Keep `MAIL_URL=null` to prevent a stale URL overriding the separate SMTP settings.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_URL=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME="YOUR_GMAIL_ADDRESS"
MAIL_PASSWORD="YOUR_GMAIL_APP_PASSWORD"
MAIL_ENCRYPTION=tls
MAIL_REQUIRE_TLS=true
MAIL_TIMEOUT=30
MAIL_FROM_ADDRESS="${MAIL_USERNAME}"
MAIL_FROM_NAME="SHOPPICK"
```

Enable Google 2-Step Verification and create a Gmail App Password for SHOPPICK. Enter the 16-character App Password without grouping spaces; do not use your normal Gmail password. App Password availability depends on your Google account/security policy. [Google App Password instructions](https://support.google.com/mail/answer/185833?hl=en), [Google SMTP settings](https://support.google.com/a/answer/176600?hl=en).

After editing:

```powershell
php artisan config:clear
```

Restart `php artisan serve` if it is already running. Register using a real recipient inbox at `http://127.0.0.1:8000/register/buyer`. Previously created Unverified Buyers can sign in and use Resend code. Check the recipient's spam folder. Verify the new code within five minutes; after resend, the old code must fail. OTP email contains SHOPPICK branding, the six digits, expiry, one-use and non-sharing instructions in both HTML and plain text.

Do not paste SMTP credentials into chat, logs or diagnostic output. Buyer SMTP failures now log only a fixed warning plus exception class, with no original exception message/trace/previous exception. The sanitized delivery exception is excluded from Laravel reporting. Buyer OTP refuses log-mail transport and the log fallback, preventing email/code contents being written there.

Changes in this follow-up: ignored `.env` (nonsecret SMTP settings and environment sender reference), `.env.example` (template/comments), `config/mail.php` (TLS/timeout options), `BuyerEmailVerification` (safe failure handling), `BuyerVerificationDeliveryException` (new), `BuyerVerificationCode` (plain-text alternative), both email templates, `GmailBuyerMailTest` (new), this document. No database migration, mobile changes, commit or push.

Verification uses Mail::fake/mocks and an in-memory SQLite database. SMTP transport construction tests confirm TLS without connecting. Real delivery cannot be claimed until you supply the Gmail address/App Password and test the inbox.

Final validation: `php artisan test --compact` passed **297 tests, 6 skipped, 0 failed, 4396 assertions**. `git diff --check` passed and `.env` is confirmed ignored by Git. No staging, commit or push.
