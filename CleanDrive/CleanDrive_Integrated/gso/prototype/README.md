# CleanDrive — GSO Dashboard Prototype

This package converts the five supplied low-fidelity GSO SVG wireframes into a modern green-themed **interactive frontend prototype**.

## Pages included
1. Dashboard Overview — operational summary, route illustration, status counts, and sample runs.
2. Manage Barangays — search, create, view, edit, enable/disable; supporting document **filename only** (files are not uploaded).
3. Manage Contractors — search, create, view, edit, enable/disable, sample operational summary.
4. Manage Schedules — select from three sample GSO routes, create/edit/delete schedules, choose collection days, review example route stops.
5. Manage Reports — five report categories, date filtering, summary/chart and CSV export from sample data.

## Run it
**Fastest**: Extract the ZIP and open `index.html` in a modern browser.

**Using PHP/XAMPP**: Copy this folder inside your XAMPP `htdocs`, start Apache, and visit `http://localhost/CleanDrive_GSO_Prototype/index.php`. You do not need MySQL to preview this prototype.

**Existing `Clean_Drive` project**: Copy it into e.g. `Clean_Drive/prototypes/gso/` so that you do not overwrite your actual project. Check the appearance before integrating any pages.

## What works
- Navigation between the five screens.
- Barangay and contractor forms and state toggles.
- Schedule creation/editing/deletion and weekday selection.
- Search/filter controls.
- Report generation and CSV download.
- Responsive layout.

## Important limitations
- **No MySQL/PHP database connection** is implemented. All changing data is kept in the browser's `localStorage` using the key `cleandrive-gso-prototype-v1`.
- The project includes an `index.php` entry point only for compatibility with PHP servers. All prototype actions run in the browser.
- All initial records, organizations, routes, operations, and vehicle identifiers are **illustrative demo data**, not authoritative operational data.
- The map is **illustrative** — no GPS provider, live truck tracking, or real geographic basemap.
- Contractor operational summary numbers are illustrative; no assignments or collector records are connected.
- Uploaded file names can be displayed, but actual file bytes are **not** uploaded or stored.
- No authentication, server-side validation, permissions, security, or multi-user persistence is provided.
- The report output is derived from demo records, not actual capstone tables.

## Next integration step
To connect to the existing `cleandrive` MySQL database, provide the latest ERD or SQL schema dump and your current `Clean_Drive` PHP backend files. Then implement server-side endpoints mapping existing tables for `barangay`, `contractor`, `route`, `schedule`, `schedule_day`, `route_stop`, `collection_run`, and related entities. Do not run newly invented CREATE TABLE statements against your existing database.

## Reset sample data
Click **Account Settings** in the sidebar and confirm resetting the demonstration data.

Original source: five SVG files supplied by the user, in uploaded order. No source SVGs are modified by this prototype.
