# CleanDrive — Organized PHP + MySQL prototype

This package adds **Super Admin** and **Barangay** dashboards based on the uploaded SVG wireframes, using the actual `cleandrive_schema(2).sql` schema. Each menu is an independent PHP folder. The shared green styling is based on the previous GSO prototype. The previous GSO frontend is preserved under `gso/prototype/` as a separate **browser-demo-only** prototype; it has **not** been connected to MySQL in this package.

## Setup in XAMPP

1. Back up any existing `Clean_Drive` folder and `cleandrive` database. **Do not overwrite existing project files or import the schema into a database that already has conflicting tables.**
2. Extract this package into `C:\xampp\htdocs\Clean_Drive` (or merge deliberately into your existing project). It is named `CleanDrive_Integrated` inside the ZIP; rename only the *outer* folder to `Clean_Drive` when placing in htdocs.
3. Start Apache and MySQL in XAMPP. Create the `cleandrive` database and import the supplied `cleandrive_schema(2).sql` **only if you need a new empty database**. The supplied SQL explicitly says it does not alter existing tables.
4. DB config: `includes/db.php` defaults to `127.0.0.1`, database `cleandrive`, username `root`, blank password. You may set `CLEANDRIVE_DB_HOST`, `CLEANDRIVE_DB_NAME`, `CLEANDRIVE_DB_USER`, and `CLEANDRIVE_DB_PASS` environment variables.
5. To create local **demonstration-only** records, open a terminal in the extracted project folder and run:

   ```bat
   C:\xampp\php\php.exe scripts\seed_demo.php --demo
   ```

   Example seeded accounts: `cd_demo_admin` and `cd_demo_barangay`. Default local-only demo password: `CleanDriveDemo!2026`; change it before sharing the system. Optionally set `CLEANDRIVE_DEMO_PASSWORD` before running. The seed inserts *new geography and demo records* even if your region/city already exists; for an existing database, prefer creating accounts manually or adapting the seed to your IDs.
6. Open `http://localhost/Clean_Drive/login.php` (or `http://localhost/CleanDrive_Integrated/login.php` if you did not rename the extracted folder).

## Folder layout

```
Clean_Drive/
  index.php, login.php, logout.php
  api/index.php             # prepared SQL queries and actions; session + CSRF validation
  includes/db.php           # PDO connection, helpers
  includes/auth.php         # session login and roles
  includes/page.php         # shared role-specific page shell
  assets/css/style.css      # reused green theme from the prior GSO prototype
  assets/js/portal.js       # UI actions, tables, forms, reports, CSV export
  super-admin/dashboard/index.php
  super-admin/manage-gso/index.php
  super-admin/account-settings/index.php
  barangay/dashboard/index.php
  barangay/manage-residents/index.php
  barangay/collection-schedules/index.php
  barangay/collection-monitoring/index.php
  barangay/complaints/index.php
  barangay/reports/index.php
  gso/prototype/            # prior GSO browser-only frontend (not MySQL)
  scripts/seed_demo.php
  database/cleandrive_schema.sql # reference copy; do not re-import into live database
```

## What actually works with MySQL

- Login and logout for Super Admin and Barangay GSO accounts, including secure `password_hash()`/`password_verify()` and session regeneration.
- Super Admin: register, edit, deactivate/reactivate GSO accounts. "Remove" safely deactivates to avoid breaking FK relations; no destructive account deletion.
- Barangay: add/edit resident accounts, activate/deactivate accounts, view assigned GSO-managed schedules and ordered route stops, review resident complaints and save response/resolution remarks, view reported vehicle locations, overview dashboard, generate reports and export CSV.
- Role and Barangay scoping for database queries; CSRF validation on POST updates.
- GPS page reads **existing** `vehicle_location` records, and does **not** claim to track a real truck continuously. It links to OpenStreetMap for positions that exist.

## Not yet implemented

- Backend-connected GSO, Contractor, Collector, and Resident workflows.
- Actual image uploads for documentation and complaint-resolution evidence. The SVG documentation UI is not implemented here; use case-specific upload validation/storage before enabling attachments.
- Collection status writes from Collector dashboard; automated geofencing; continuous GPS live updates; notifications; marketplace.
- Full browser-based integration tests and production security hardening. This is a capstone prototype, not production software.

## Troubleshooting

- **No linked role profile:** User exists in `user` but lacks a record in `super_admin` or `barangay_gso`.
- **Empty dashboard:** No records for the signed-in Barangay; run local seed on a clean database or create data through the appropriate existing modules.
- **Database errors:** Check PDO MySQL extension, XAMPP MySQL status, schema table/column names, credentials, and browser network response.
- **404 links:** Folder should be directly inside `htdocs` and the browser address should use the exact folder name.
- **Security note:** `scripts/seed_demo.php` requires CLI and `--demo`, not callable through a normal browser. Remove it from publicly accessible deployments.

## Contractor + Resident extension (new)

This version adds the **10 newly uploaded Contractor and Resident wireframe modules** to the previous Super Admin + Barangay package, following the same green design and shared style. Contractor's two Manage Vehicles SVGs are variants of one page. Every menu is in a different role subfolder; this package **does not rename or replace the database**.

### New paths

- `contractor/dashboard/`, `contractor/manage-collectors/`, `contractor/manage-vehicles/`, `contractor/assignments/`, `contractor/collection-schedules/`, `contractor/account-settings/`
- `resident/dashboard/`, `resident/collection-schedule/`, `resident/track-truck/`, `resident/complaints/`, `resident/marketplace/`, `resident/account-settings/`
- `api/extended.php` implements role-scoped Contractor and Resident API operations.
- `assets/js/portal_extended.js` renders interactive forms, tables, filters, details, and navigation.

### Database functions

- Contractor registers and edits **collector** accounts (linked `user` records, password hashes), registers/edits **vehicles**, and creates/edits **collection assignments**. Assignments validate contractor ownership of collector and vehicle. Contractor schedules are GSO-managed and displayed read-only. Fleet GPS shows only existing `vehicle_location` records.
- Resident views **barangay-scoped schedule days and route stops**, sees recorded vehicle positions, submits/view **complaints**, posts and edits their own **marketplace listings**, and sends messages to other listing owners using `message`. A resident can only modify their own listing.
- Contractor and Resident can log in and change their passwords. All write actions use sessions, CSRF validation, and prepared SQL statements.

### Local demonstration accounts

On a **new empty demo database only**, run `php scripts/seed_demo.php --demo` as described above. Its existing demo data includes `cd_demo_contractor` and `cd_demo_resident1` (as well as `cd_demo_resident2`, `cd_demo_resident3`) with the same local password configured by the seed script. Do not run seed on a production or important database, and change demo passwords. The Collector account is seeded but a Collector portal is not implemented in this package.

### Known limitations

- The original GSO dashboard is still **frontend-only** and stored under `gso/prototype/`.
- The SVG performance chart is represented by live counts and a clear no-data message where a genuine performance dataset is not available.
- Map panels use last-reported coordinates and a link to OpenStreetMap; they are **not** a continuously updated embedded map, route tracing engine, or geofencing system.
- Complaint images and marketplace image uploads are not implemented. Marketplace messages are basic one-way entries; full threaded chat is not implemented.
- Real MySQL end-to-end operation must be tested with your local XAMPP setup. PHP and JS syntax checks do not prove the database workflow works.
- This package is a complete project bundle, not an overwrite patch. Back up your project and merge carefully, especially if you edited the earlier version.
