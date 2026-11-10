# Salon Appointment Scheduling and Customer Management System

A web-based management system that lets a salon record and monitor all appointments and customer records in one place, replacing notebooks and scattered chat messages.

**Course:** Web Management System, BSCS-3C
**Author:** Fedoc, Cyrene Nathalie B.
**Submitted to:** Prof. Wendy Anas

---

## Table of contents

1. [Project status](#1-project-status)
2. [Problem and solution](#2-problem-and-solution)
3. [Features](#3-features)
4. [User roles](#4-user-roles)
5. [Architecture](#5-architecture)
6. [Tech stack](#6-tech-stack)
7. [Repository layout](#7-repository-layout)
8. [Database design](#8-database-design)
9. [Prerequisites](#9-prerequisites)
10. [Installation](#10-installation)
11. [Configuration](#11-configuration)
12. [Running the system](#12-running-the-system)
13. [Modules reference](#13-modules-reference)
14. [Business rules](#14-business-rules)
15. [Authentication and access control](#15-authentication-and-access-control)
16. [Security safeguards](#16-security-safeguards)
17. [Testing and verification](#17-testing-and-verification)
18. [Known limitations](#18-known-limitations)
19. [Troubleshooting](#19-troubleshooting)
20. [Design decisions](#20-design-decisions)
21. [Future work](#21-future-work)

---

## 1. Project status

| Module | Status |
|--------|--------|
| Database schema and seed data | Planned |
| Login / logout and role guard | Planned |
| Services and stylists management | Planned |
| Customer management and search | Planned |
| Appointment scheduling (overlap + daily limit) | Planned |
| Appointment status monitoring | Planned |
| Customer booking portal | Planned |
| Reviews and vouches | Planned |
| Dashboard and reports | Planned |

Update this table as each module is finished (`Planned` → `In progress` → `Done`).

## 2. Problem and solution

**Problem.** Small salons take bookings through walk-ins, calls, texts and social media, and write them in notebooks. This causes double bookings, outdated schedules after cancellations, scattered customer records, and manual end-of-day counting.

**Solution.** One shared, searchable system where:

- Customers request appointments online (service, stylist, date, time).
- Receptionists record walk-in and phone bookings and confirm online requests.
- The system blocks overlapping bookings for the same stylist and refuses bookings once the daily limit is reached.
- Every appointment has a status: `pending`, `confirmed`, `completed`, `cancelled`, `no-show`.
- Customers leave a rating and short review after a completed service; approved reviews are shown as customer vouches.
- A dashboard and reports summarize daily and monthly activity.

**Out of scope:** online payments and SMS reminders.

## 3. Features

1. Login and logout (role-based access)
2. Dashboard (today's appointments, counts per status)
3. Customer management (profiles, appointment history)
4. Services and stylists management (price, duration)
5. Appointment scheduling (overlap and daily-limit check)
6. Appointment status monitoring
7. Customer reviews (approval by admin, shown as vouches)
8. Search and filtering (name, date, stylist, service, status)
9. Reports (daily/monthly totals, popular services, average ratings)

## 4. User roles

| Role | Can do |
|------|--------|
| **Admin / Owner** | Manage users, services, prices, stylists and the daily appointment limit; view all appointments and customers; approve or remove reviews; generate reports |
| **Receptionist / Staff** | Register and update customers; create, reschedule and cancel appointments; confirm online requests; update statuses; view the daily schedule |
| **Customer** | Create an account; view services and stylists; request an appointment; view, cancel or request to reschedule own appointments; review completed services. Sees only their own records |

## 5. Architecture

A layered, server-rendered PHP application. Pages are split into a **frontend** part (HTML/CSS/JS the user sees) and a **backend** part (PHP that validates, applies business rules and talks to MySQL).

```
 FRONTEND                         BACKEND                       DATA
 ─────────────────────────        ───────────────────────       ──────────────
 Browser                          actions/  (form handlers)
   │  HTML5 + CSS + JS              │  validate, call rules
   ▼                                ▼
 Pages / views  ── POST ───────▶  includes/functions.php  ───▶  MySQL (salon_db)
 (admin/, staff/, customer/,        (overlap check, daily        via PDO prepared
  public/)                           limit, helpers)              statements
   ▲                                │
   └──── redirect + flash message ──┘
                                    includes/auth.php  (session, role guard)
                                    includes/db.php    (PDO connection)
```

**Request flow (example: staff books an appointment)**

1. Staff opens `staff/appointment_form.php` (view) and submits the form.
2. The form posts to `actions/appointment_save.php`.
3. The action checks the session role, validates input and CSRF token.
4. `functions.php` runs the overlap check and the daily-limit check.
5. If both pass, the appointment is saved; otherwise an error message is stored.
6. The user is redirected back to the page and sees a success or error message.

## 6. Tech stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML5, CSS3, vanilla JavaScript |
| Backend | PHP 8+ (plain PHP, PDO) |
| Database | MySQL / MariaDB |
| Local server | XAMPP (Apache + MySQL) |
| Version control | Git and GitHub |

## 7. Repository layout

```
salon-system/
├── README.md
├── .gitignore
├── config/
│   ├── config.php                 # app name, base URL, timezone
│   └── database.example.php       # copy to database.php (git-ignored)
├── database/
│   ├── schema.sql                 # tables and constraints
│   └── seed.sql                   # sample services, stylists, admin account
├── includes/                      # BACKEND: shared logic
│   ├── db.php                     # PDO connection
│   ├── auth.php                   # session start, login check, require_role()
│   ├── functions.php              # overlap check, daily limit, CSRF, helpers
│   ├── header.php                 # FRONTEND: shared layout
│   ├── sidebar.php
│   └── footer.php
├── actions/                       # BACKEND: form handlers (POST only)
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── appointment_save.php
│   ├── appointment_status.php
│   ├── customer_save.php
│   ├── service_save.php
│   ├── stylist_save.php
│   ├── review_save.php
│   └── review_moderate.php
├── public/                        # FRONTEND: no login needed
│   ├── index.php                  # landing page, services, approved reviews
│   ├── login.php
│   └── register.php
├── admin/                         # FRONTEND pages for the admin role
│   ├── dashboard.php
│   ├── users.php
│   ├── services.php
│   ├── stylists.php
│   ├── appointments.php
│   ├── customers.php
│   ├── reviews.php
│   ├── settings.php               # daily appointment limit
│   └── reports.php
├── staff/                         # FRONTEND pages for the receptionist role
│   ├── dashboard.php
│   ├── customers.php
│   ├── appointments.php
│   ├── appointment_form.php
│   └── schedule.php
├── customer/                      # FRONTEND pages for the customer role
│   ├── dashboard.php
│   ├── book.php
│   ├── my_appointments.php
│   ├── review.php
│   └── profile.php
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── img/
└── docs/
    ├── Salon_Project_Proposal.pdf
    └── erd.png
```

## 8. Database design

Database name: `salon_db`

| Table | Main columns |
|-------|--------------|
| `users` | id, name, email (unique), password_hash, role (`admin` / `staff` / `customer`), created_at |
| `customers` | id, user_id (nullable, for walk-ins without an account), full_name, phone, email, notes, created_at |
| `stylists` | id, full_name, phone, is_active |
| `services` | id, name, price, duration_minutes, is_active |
| `stylist_services` | stylist_id, service_id (which stylist performs which service) |
| `appointments` | id, customer_id, stylist_id, service_id, appointment_date, start_time, end_time, status, source (`online` / `walk-in` / `phone`), notes, created_by, created_at |
| `reviews` | id, appointment_id (unique), customer_id, rating (1–5), comment, is_approved, created_at |
| `settings` | setting_key, setting_value (e.g. `daily_appointment_limit`) |

**Relationships:** a customer has many appointments; an appointment belongs to one stylist and one service; a completed appointment has at most one review.

## 9. Prerequisites

- XAMPP (PHP 8.0 or newer, Apache, MySQL/MariaDB)
- A web browser
- Git
- A code editor (e.g. VS Code)

## 10. Installation

1. Install XAMPP and start **Apache** and **MySQL** from the control panel.
2. Clone the repo into the XAMPP web root:
   ```bash
   cd C:\xampp\htdocs
   git clone <your-repo-url> salon-system
   ```
3. Open phpMyAdmin (`http://localhost/phpmyadmin`) and create a database named `salon_db`.
4. Import `database/schema.sql`, then `database/seed.sql`.

## 11. Configuration

Copy the example config and edit it:

```bash
copy config\database.example.php config\database.php
```

| Setting | XAMPP default |
|---------|---------------|
| `DB_HOST` | `localhost` |
| `DB_NAME` | `salon_db` |
| `DB_USER` | `root` |
| `DB_PASS` | *(empty)* |

`config/database.php` is git-ignored so credentials are never committed.

The daily appointment limit is not set in a file; the admin changes it in **Settings** (stored in the `settings` table).

## 12. Running the system

Open `http://localhost/Salon-Appointment-Scheduling-and-Customer-Management-System/public/login.php`.

The public landing page is not implemented yet; use the login URL above. If you rename the project folder inside `htdocs`, update `BASE_URL` in `config/config.php` to match.

**Default accounts** (change the passwords after first login):

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@salon.test | admin123 |
| Staff | staff@salon.test | staff123 |

Customers register their own accounts from the Register page.

## 13. Modules reference

| Module | Who | Where |
|--------|-----|-------|
| Login / Logout | All | `public/login.php`, `actions/logout.php` |
| Dashboard | Admin, Staff, Customer | `*/dashboard.php` |
| Customer management | Admin, Staff | `staff/customers.php`, `admin/customers.php` |
| Services and stylists | Admin | `admin/services.php`, `admin/stylists.php` |
| Appointment scheduling | Staff, Customer | `staff/appointment_form.php`, `customer/book.php` |
| Status monitoring | Admin, Staff | `staff/appointments.php`, `actions/appointment_status.php` |
| Reviews | Customer, Admin | `customer/review.php`, `admin/reviews.php` |
| Search and filtering | Admin, Staff | appointment and customer list pages |
| Reports | Admin | `admin/reports.php` |

## 13a. Appointment statuses

| Status | Meaning | Blocks the time slot? |
|--------|---------|----------------------|
| `pending` | Online request waiting for staff confirmation | Yes |
| `confirmed` | Accepted by staff | Yes |
| `completed` | Service was done; customer can now review | Yes |
| `cancelled` | Cancelled by customer or staff | No |
| `no-show` | Customer did not come | No |

## 14. Business rules

**Overlap check** (same stylist, same date, ignoring cancelled and no-show):

```sql
SELECT COUNT(*) FROM appointments
WHERE stylist_id = :stylist_id
  AND appointment_date = :date
  AND status NOT IN ('cancelled', 'no-show')
  AND :new_start < end_time
  AND :new_end   > start_time;
```

If the count is greater than 0, the booking is rejected. Back-to-back bookings (one ends exactly when the next starts) are allowed.

**Daily limit:** count that date's active appointments and compare with `settings.daily_appointment_limit`. Reject the booking when the limit is reached.

**End time:** `end_time = start_time + services.duration_minutes`.

**Reviews:** only allowed for the customer's own `completed` appointments, one per appointment. Shown publicly only after the admin approves.

**Online requests:** saved as `pending`; staff confirm them.

## 15. Authentication and access control

- Sessions store the user id and role after login.
- Every protected page starts with `require_role('admin')`, `require_role('staff')` or `require_role('customer')`.
- Customers can only query records where `customer_id` matches their own account.
- Unauthorized access redirects to the login page.

## 16. Security safeguards

- Passwords hashed with `password_hash()` and verified with `password_verify()`.
- All SQL uses PDO prepared statements.
- Output escaped with `htmlspecialchars()`.
- CSRF token on every POST form.
- Server-side validation on all inputs (dates, times, ratings, required fields).
- Session ID regenerated on login.
- `config/database.php` excluded from version control.

## 17. Testing and verification

Manual test checklist:

- [ ] Two bookings for the same stylist at overlapping times are rejected.
- [ ] Back-to-back bookings are allowed.
- [ ] A cancelled booking frees its time slot.
- [ ] A booking is refused once the daily limit is reached.
- [ ] A customer cannot open another customer's appointments.
- [ ] Staff cannot open admin pages.
- [ ] A review cannot be submitted for a non-completed appointment.
- [ ] Only approved reviews appear on the public page.
- [ ] Reports match the appointment counts in the database.

## 18. Known limitations

- No online payments.
- No SMS or email reminders.
- Reschedule by customers is a request that staff must apply.
- Designed for a single salon branch.
- Runs on a local XAMPP server; no production hosting setup.

## 19. Troubleshooting

| Problem | Fix |
|---------|-----|
| Blank page or PHP errors | Check `C:\xampp\apache\logs\error.log`; make sure PHP is 8.0+ |
| "Could not connect to database" | MySQL not running, or wrong values in `config/database.php` |
| Login always fails | Re-import `seed.sql`; confirm the `users` table has the admin row |
| Page not found | Check that the project folder is inside `htdocs` and that its URL prefix matches `BASE_URL` in `config/config.php`; open `public/login.php` directly |
| Wrong times shown | Set the timezone in `config/config.php` (e.g. `Asia/Manila`) |

## 20. Design decisions

- **Plain PHP over a framework:** matches the course requirement (HTML5, CSS, PHP, MySQL) and keeps the code easy to explain.
- **`actions/` separate from pages:** pages only display; actions only process. This keeps the frontend and backend parts easy to tell apart.
- **Business rules in one file:** the overlap and limit checks are used by both staff and customer booking, so they live in `functions.php`.
- **Walk-in customers without accounts:** `customers.user_id` is nullable so staff can record customers who never register online.
- **Cancelled and no-show free the slot:** keeps the schedule accurate without deleting history.

## 21. Future work

- Online payments
- SMS and email reminders
- Multiple branches
- Calendar view with drag-and-drop rescheduling
- Customer loyalty points

---

*Built for the Web Management System course: Problem first, solution second, technology last.*