# Barangay E-Services and Community Concern Management System

A web system for a single barangay. The administrator sets the barangay's name, city or municipality, province, logo and office hours under **Settings**, and those details appear in the navigation bar, dashboards, footer, emails and printed reports. The sample data is configured as Barangay San Isidro, Sta. Maria, Laguna; change it in Settings. Residents check document requirements, request documents and report community concerns online, then follow each one's status. Barangay staff review, process and resolve them; the administrator manages accounts, reference data, reports and activity logs.

Built with PHP 8, MySQL/MariaDB through MySQLi (prepared statements only), HTML, CSS, JavaScript and Bootstrap 5.3.

## Set up on XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy the `Barangay-EServices` folder into `C:\xampp\htdocs\`.
3. Open http://localhost/phpmyadmin, select **Import**, choose `database/barangay_eservices.sql` and click **Import**.
   This creates the `barangay_eservices` database with sample records.
   (Alternative: run `C:\xampp\php\php.exe database\seed.php` from the project folder to rebuild it.)
   Already imported an older copy? Import `database/upgrade_add_settings.sql` instead to add the Settings table and keep your data.
4. Copy `config/config.sample.php` to `config/config.php` and edit it:
   - `db`: leave as is for a default XAMPP install (user `root`, empty password).
   - `mail`: your SMTP settings (see below).
5. Open http://localhost/Barangay-EServices/ and sign in with an account from `DEMO_CREDENTIALS.txt`.
6. Sign in as the administrator, open **Settings**, and enter your barangay's name, city or municipality, province, office hours and logo.

The app detects its own folder name, so it also works if the folder is renamed.

### Email (SMTP) for verification

Verification emails are sent with PHPMailer (included in `vendor/phpmailer`). With Gmail:

1. Turn on 2-Step Verification on the Google account.
2. Create an **App password** at https://myaccount.google.com/apppasswords.
3. In `config/config.php`:

```php
'mail' => [
    'transport'  => 'smtp',
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => 'your.address@gmail.com',
    'password'   => 'the 16-character app password',
    'from_email' => 'your.address@gmail.com',
    'from_name'  => '',   // empty = "<Barangay name> E-Services" from Settings
],
```

If the presentation machine has no internet, set `'transport' => 'log'`: each email is then saved as an HTML file in `storage/mail/` (open it in a browser and click the link). Use `smtp` for the graded demo, since the instructions require an actual email.

`config/config.php` is not part of the submission ZIP, so no SMTP password is submitted.

## Screens

- **Landing page** (`index.php`, public): barangay details from Settings, available documents with fees, how to request a document, how to report a concern, Register / Sign in. Requirements (`services.php`) are public; submitting a request or concern needs a verified resident account. A visitor who clicks "Sign in to request" is returned to the request form after signing in.
- **Signed-in layout**: dark red sidebar grouped by role, top header with breadcrumb and the user, white content panels. On phones the sidebar becomes a slide-out menu. Staff see counts of waiting requests and queue items beside the menu items.
- **Dashboards**: barangay banner with greeting, date and office hours; summary figures; "waiting" cards; recent requests and concerns; a monthly activity chart (server-drawn SVG with hover details and a "Show as table" view).
- **Residents** (`residents.php`, staff and admin): resident directory with search and purok/verification filters, and links to each resident's requests and concerns.

The sidebar only lists pages the role may open, and every page still checks the role on the server.

## Roles

| Role | Can do |
|---|---|
| Resident | Register, verify email, view requirements, request documents (edit or cancel while Pending), report concerns (edit or withdraw while Submitted), track status, close resolved concerns |
| Barangay Official / Staff | Review, approve/reject and release requests; review, prioritize, assign, work on and resolve concerns; triage queue; resident directory (read-only); reports; archive finished records |
| Administrator | Barangay settings (name, location, logo, office hours); manage user accounts, document types and requirements, concern categories; view all records; restore archived records; reports; activity logs |

Every page checks the role on the server (`require_role()` in `app/lib/auth.php`). Residents can only load their own records: the list queries filter by `resident_id`, and opening another resident's record by id returns "Record not found" and is logged.

## Workflows

**Document request:** Pending → Under Review → Approved (or Rejected) → Processing → Ready for Pickup → Released. The resident may cancel while Pending.

**Community concern:** Submitted → Under Review → Assigned → In Progress → Resolved → Closed. Staff may reject an invalid report during review; the resident may withdraw while Submitted. Only the assigned staff member can start and resolve it.

The allowed transitions are defined in `app/lib/workflow.php`. A change is applied only if the transition is allowed for the user's role, and only if the record is still in the status the user saw (`UPDATE ... WHERE status = ?`). This blocks invalid actions such as releasing an unapproved request, and prevents the same transaction from being processed twice. Every change is stored in `request_status_history` / `concern_updates` and in `activity_logs`.

## Data structure: priority queue

`app/lib/ConcernTriageQueue.php` wraps PHP's `SplPriorityQueue` (a binary max-heap). Staff open **Triage queue** and press **Take next concern**, which extracts the most urgent unassigned concern and assigns it to them.

- Key: `[queue priority, −submitted time, −id]` — higher priority first; within the same priority, oldest first.
- Queue priority = priority set by staff + 1 level for every 3 days waiting (capped at Urgent), so routine reports are not left behind.
- `insert` O(log n), `extract` O(log n), `peek` O(1), `count` O(1); building the queue from n rows O(n log n).
- The queue page shows the heap order next to the plain arrival order, plus the complexity table.

Associative arrays (hash maps) are also used for O(1) grouping: requirements by document type (`services.php`) and status counts in reports (`reports.php`).

## Database

`database/schema.sql` (structure) and `database/barangay_eservices.sql` (structure + sample data). Tables: `roles`, `puroks`, `users`, `barangay_settings` (one row: the barangay profile), `email_verifications`, `document_types`, `document_requirements`, `document_requests`, `request_status_history`, `concern_categories`, `concerns`, `concern_updates`, `activity_logs`. All tables use InnoDB with primary keys, foreign keys, unique keys and CHECK constraints. Requirements are a separate table (one row per requirement) instead of a list column. `fee_amount` on a request stores the fee at the time of the request, so later fee changes do not alter past records.

## Security

- Passwords: `password_hash()` / `password_verify()`; 5 failed sign-ins lock the account for 15 minutes.
- Sessions: HttpOnly + SameSite cookie, strict mode, new session id on sign-in, 30-minute idle timeout; the user is re-read from the database on each request, so deactivation takes effect immediately.
- Email verification: random 256-bit token, only its SHA-256 hash is stored, expires after 60 minutes, single use, older links are invalidated on resend, 60-second resend cooldown.
- CSRF token on every form that changes data (including sign-out).
- All queries with user input use MySQLi prepared statements (`app/lib/db.php`).
- Server-side validation (`app/lib/validator.php`); all output escaped with `e()`.
- Errors are logged; users see a generic page without database details. Content-Security-Policy and X-Frame-Options headers.
- `.htaccess` files block web access to `app/`, `config/`, `database/`, `storage/` and `vendor/`.
- Logo uploads: PNG, JPG or WebP only (checked from the file contents, not the name), up to 2 MB, saved under a random name in `uploads/logo/`. SVG is refused. `uploads/.htaccess` serves only image files, so nothing uploaded can run as code.

## Project layout

```
index.php, services.php        Public landing page and requirements
residents.php                  Resident directory (staff and admin)
auth/                          Register, sign in/out, email verification
requests.php, request.php      Request list and detail (all roles, scoped by role)
concerns.php, concern.php      Concern list and detail (all roles, scoped by role)
request-action.php, concern-action.php   Status changes, archive/restore (POST only)
resident/                      Resident dashboard and forms
staff/                         Staff dashboard and triage queue
admin/                         Settings, users, document types, categories, logs, dashboard
reports.php                    Filtered printable reports
app/lib/                       Database, settings, auth, security, validation, workflows, mailer, priority queue
uploads/logo/                  Uploaded barangay logo
app/views/                     Layout (sidebar/header/footer), welcome banner, monthly chart, partials
assets/                        CSS, JavaScript, Public Sans font, Bootstrap
vendor/phpmailer/              PHPMailer 6.9.3
```

## Third-party resources

- [Bootstrap 5.3.3](https://getbootstrap.com) (MIT) — layout and components
- [PHPMailer 6.9.3](https://github.com/PHPMailer/PHPMailer) (LGPL 2.1) — SMTP email
- [Public Sans](https://public-sans.digital.gov) (SIL Open Font License) — typeface
