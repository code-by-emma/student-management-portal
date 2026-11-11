# Student Management Portal (SMP)

A full-stack, role-based Student Management Portal built with **PHP 8 (OOP-style, MVC-inspired), MySQL, and Tailwind CSS**. Three roles — **Administrator, Teacher, Student** — each get their own dashboard and permissions.

## Features

- **Auth & security**: bcrypt password hashing, show/hide toggle on every password field, session-based login with "remember me", forgot/reset password, CSRF tokens on every form, session timeout after 30 minutes idle, role-based access control (`require_role()`), audit logging, secure file upload validation (extension + real MIME sniffing).
- **Admin**: manage students, teachers, other admins, courses, classes, grade levels, timetable, school fees (see below), view reports (attendance / grades / enrollment with CSV export), post announcements, view audit logs, account settings.
- **Teacher**: view assigned courses, take attendance, post/grade assignments, create exams and enter grades, view weekly timetable, send announcements to their classes.
- **Student**: view enrolled courses, check attendance history, submit assignments and see feedback, view grades and download a CSV report card, view weekly timetable, view/pay school fees, receive announcements/notifications.
- Responsive UI, dark/light mode, in-app notifications, global search per role.

### Grade levels & sections

Classes are organized under a master **Grade Level** list (e.g. "Grade 1"), managed from Admin → Grade Levels. Each class is then a section/arm of a grade level with its own label (e.g. "Grade 1" + section "A" = "1A"), managed from Admin → Classes. Renaming a grade level automatically updates every class under it. A grade level can't be deleted while it still has fee structures attached (to protect payment history) — classes under it are kept and simply unlinked instead.

### School fees

- **Admin** (Admin → Fees): create fee structures (fee type, amount, term, academic year, optional due date, either for one grade level or "all grade levels"), then click **Generate Invoices** to bill every student in scope — safe to click again later for newly enrolled students, it skips anyone already invoiced. Record cash/bank-transfer payments manually from the Payments Ledger tab, which also has a CSV export.
- **Student** (Student → Fees): see every invoice, amount paid/balance, and payment history. If online payments are configured (see below), a **Pay Online** button appears on anything with a balance.
- **Online payments (optional)**: the app integrates [Paystack](https://paystack.com) for card payments. Manual recording always works with zero setup; online payment is opt-in. To enable it, get your API keys from your Paystack dashboard and set them as environment variables (or edit `config/payment.php` directly):
  ```
  PAYSTACK_PUBLIC_KEY=pk_test_xxxxx
  PAYSTACK_SECRET_KEY=sk_test_xxxxx
  ```
  Use your test keys first. Every payment is verified **server-side** against Paystack's own API (`student/fee-pay-verify.php`) before it's credited — the client-side "success" callback is never trusted on its own, and a payment reference can never be credited twice (enforced at the database level). Leaving the keys blank is completely safe: the "Pay Online" button simply doesn't appear, and manual recording keeps working.

## Requirements

- PHP 8.0+ with the `pdo_mysql`, `fileinfo`, and `mbstring` extensions (all enabled by default in XAMPP/WAMP/MAMP)
- MySQL 8+ or MariaDB 10.4+
- Apache (or any server that can run PHP) — XAMPP/LAMP/WAMP all work out of the box

## Installation

1. **Copy the project** into your server's web root, e.g. `htdocs/smp` (XAMPP) or `/var/www/html/smp` (LAMP).

2. **Create the database.** Import the schema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   This creates the `smp_db` database, all tables, and a couple of sample classes/subjects (no user accounts yet — see next step).

3. **Configure the database connection.** Edit the fallback values at the top of `config/database.php` to match your setup (defaults are already correct for a stock XAMPP install — `127.0.0.1` / `smp_db` / `root` / no password):
   ```php
   $DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
   $DB_NAME = getenv('DB_NAME') ?: 'smp_db';
   $DB_USER = getenv('DB_USER') ?: 'root';
   $DB_PASS = getenv('DB_PASS') ?: '';
   ```

4. **Seed demo accounts.** User passwords must be hashed by PHP itself, so run this once:
   ```bash
   php database/seed.php
   ```
   or visit `http://localhost/smp/database/seed.php` in your browser. It prints the demo login credentials and creates a sample teacher, student, course, timetable slot, and exam so the portal isn't empty on first login. It's safe — it refuses to run twice.

5. **Set folder permissions** so uploads work:
   ```bash
   chmod -R 755 uploads/
   ```

6. **Visit the app**: `http://localhost/smp/`

### Demo accounts (created by `seed.php`)

| Role    | Username | Password       |
|---------|----------|-----------------|
| Admin   | `admin`  | `Admin@123`     |
| Teacher | `jokoro` | `Teacher@123`   |
| Student | `cumeh`  | `Student@123`   |

**Change these passwords immediately in production** (each role can do this from *My Profile → Change password*).

## Upgrading an existing install

If you already have this app running with real data (not a fresh install), **do not re-run `schema.sql`** — it would try to recreate tables that already exist. Instead:

1. Replace your project files with the new ones (every file here is safe to overwrite — nothing that holds your data was changed in a breaking way).
2. Run the migration once to add Grade Levels and the Fees tables to your *existing* database, non-destructively:
   ```bash
   php database/migrate_v2.php
   ```
   or visit `http://localhost/smp/database/migrate_v2.php` in your browser. It only adds new tables/columns and never touches your existing students, grades, attendance, etc. It's safe to run more than once — anything already applied is automatically skipped.
3. Log in as admin → **Grade Levels** to review the auto-seeded list (Grade 1–12 by default — rename, reorder, add, or remove to match your school), then **Classes** to assign each existing class to a grade level (classes created before the upgrade aren't linked automatically unless their name matched a seeded level exactly, e.g. an existing class literally named "Grade 9").

## Project structure

```
smp/
├── admin/              Admin-only pages: students, teachers, admins, courses, classes, grade-levels,
│                          timetable, fees, reports, announcements, audit logs, settings
├── teacher/             Teacher-only pages (dashboard, courses, attendance, assignments, submissions, grades, timetable, announcements)
├── student/              Student-only pages: dashboard, courses, attendance, assignments, grades, timetable,
│                          fees.php (view/pay) + fee-pay-verify.php (server-side payment verification)
├── auth/                Login, logout, register (student self-signup), forgot/reset password
├── config/
│   ├── app.php          App bootstrap: sessions, CSRF, BASE_URL, constants, error display
│   ├── database.php     Your DB credentials — edit this first
│   └── payment.php      Paystack keys + currency settings — edit this to enable online fee payment
├── includes/            Shared PHP: functions.php, auth.php (RBAC), header/sidebar/footer, and
│                          shared profile/notifications/search pages reused across all 3 roles
├── database/
│   ├── schema.sql       Full normalized schema + sample rows — fresh installs only
│   ├── seed.php         One-time script that creates demo accounts with hashed passwords
│   └── migrate_v2.php   One-time, idempotent upgrade script for existing installs (adds grade levels + fees)
├── assets/
│   ├── css/style.css     Small set of custom classes (`.card`, `.data-table`, `.badge`) on top of Tailwind
│   └── js/main.js        Dark mode toggle, mobile sidebar, password show/hide, flash-toast auto-dismiss, confirm dialogs
├── uploads/               Profile pictures, assignment submissions, course materials (created for you; keep writable)
└── index.php              Entry point — redirects to the right dashboard or to login
```

## Database schema (overview)

`roles → users → {students, teachers}`, `grade_levels → classes (grade level + section, e.g. "Grade 1" + "A") → courses (subject + teacher + class) → enrollments`, `attendance`, `assignments → assignment_submissions`, `exams → grades`, `results` (aggregated per term — populate this yourself if you need report-card ranking), `timetables`, `announcements`, `notifications`, `audit_logs`, `fee_structures → student_fees → fee_payments` (a fee structure is billed to students as individual invoices, each of which can have multiple payments against it). Every foreign key cascades or nulls sensibly on delete — **except** `fee_structures.grade_level_id`, which deliberately blocks deletion of a grade level that still has fee data under it, so payment history can never be wiped by an unrelated action. See `database/schema.sql` for the full DDL with comments.

## Notes & things you may want to extend

- **Email notifications**: the app currently only creates in-app notifications (`notifications` table). To send real emails (e.g. via PHPMailer or your SMTP provider), hook into the `notify()` helper in `includes/functions.php`.
- **PDF/Excel export**: reports and report cards currently export as **CSV**, which opens natively in Excel/Google Sheets without adding a heavy PDF-generation library. If you need branded PDF report cards, consider adding a library like `dompdf` via Composer and rendering `student/grades.php`'s data into an HTML template.
- **Results table**: the `results` table (final aggregated grade per student/course/term) exists in the schema but isn't populated automatically — you'd typically compute it from `grades` at the end of a term via a small script or admin action.
- **Password policy**: minimum 8 characters is enforced on change-password; tighten this in `includes/profile-page.php` and `auth/reset-password.php` if your institution needs more.
- This project uses the Tailwind CDN script for simplicity — for production, consider compiling Tailwind via its CLI/PostCSS instead of loading it from a CDN on every page.
- **New students and fee invoices**: "Generate Invoices" on a fee structure only bills students who don't already have that invoice, so it's meant to be clicked again whenever new students join a grade level mid-term — there's no need to track who's new yourself.

## Security checklist before going live

- [ ] Change all demo passwords
- [ ] Switch Paystack from test keys (`pk_test_...` / `sk_test_...`) to live keys only after a real test payment has worked end-to-end
- [ ] Set `'secure' => true` in the session cookie params (`config/app.php`) once served over HTTPS
- [ ] Turn off `APP_DEBUG` (leave the `display_errors` env var unset in production)
- [ ] Restrict `uploads/` from executing scripts (add a `.htaccess` with `php_flag engine off` in that folder on Apache)
- [ ] Take regular MySQL backups (`mysqldump`)

   ## Quick setup

   1. Copy `config/mail.sample.php` to `config/mail.php` and fill in your SMTP details
      (for Gmail, use an App Password). Without it, the password-reset page shows
      "email delivery isn't set up" instead of sending.
   2. Run `composer install` to install PHPMailer.
   3. Run `npm install` only if you plan to rebuild the Tailwind CSS.
   4. Import `database/schema.sql`, then run `database/seed.php` once for demo accounts.
      The demo passwords are for local testing only. Change them on any real site.

      > Learning project. Review and test before using with real student data or real payments.
      > Demo logins in `database/seed.php` are for local testing only. Change them on any real site.
