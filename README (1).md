# 🗳️ Online Voting System

A web-based platform for conducting **Class Representative (CR) elections** at **Shri Ramswaroop Memorial University (SRMU)**. Students register, complete a verified profile, and vote for candidates — while admins manage elections, verify student identities, and publish results.

<p align="center">
  <img src="college-banner.png" alt="SRMU Online Voting System" width="600">
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white">
  <img alt="HTML5" src="https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white">
  <img alt="CSS3" src="https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white">
  <img alt="License" src="https://img.shields.io/badge/license-MIT-green">
</p>

---

## 📖 Table of Contents

- [About](#-about)
- [Features](#-features)
- [Tech Stack](#️-tech-stack)
- [Database Schema](#️-database-schema)
- [Project Structure](#-project-structure)
- [Setup & Installation](#️-setup--installation)
- [Usage Flow](#-usage-flow)
- [Security Notes](#-security-notes)
- [Future Improvements](#-future-improvements)
- [Author](#-author)
- [License](#-license)

---

## 📌 About

This project was built as a college submission to digitize the CR (Class Representative) election process at SRMU — replacing manual/paper-based voting with a secure, verifiable online system. Every student profile is **manually reviewed by an admin** (including a live selfie photo) before the account is marked verified and allowed to vote, keeping the process trustworthy for a real academic election.

---

## ✨ Features

### 🎓 For Students
- **Secure registration & login** — passwords hashed with `password_hash()`, all queries use prepared statements to prevent SQL injection
- **Profile completion** — students submit their name, age, roll number, ERP ID, course, section, and a selfie (captured live via webcam or uploaded from gallery)
- **Admin-verified identity** — profiles and selfies are manually reviewed and approved by an admin before a student can vote
- **Vote in elections** — cast votes for CR candidates once verified
- **View results** — see live/published election results
- **Edit profile** — update submitted details
- **Forgot password** flow for account recovery

### 🛡️ For Admins
- Separate, isolated admin login
- Review student photos and profiles before verifying an account
- Admin dashboard to manage students, candidates, and elections
- Manage and publish results

### ⚙️ General
- Global loading-screen overlay for smoother page transitions
- Responsive form UI with password visibility toggles and live selfie capture (`getUserMedia`)

---

## 🛠️ Tech Stack

| Layer      | Technology                     |
|------------|---------------------------------|
| Backend    | PHP (procedural, `mysqli`)     |
| Database   | MySQL / MariaDB                |
| Frontend   | HTML5, CSS3, vanilla JavaScript |
| Server     | Apache (via XAMPP)              |

---

## 🗄️ Database Schema

The database (`online-voting`) consists of **9 tables**. Full structure is in [`database.sql`](./database.sql) (schema only — no personal data).

### `users`
Core student accounts and voting state.
| Column | Type | Notes |
|---|---|---|
| `id` | `int` PK, auto-increment | |
| `name`, `email`, `phone` | `varchar` | `phone` is `NOT NULL` |
| `password` | `varchar(255)` | bcrypt hash via `password_hash()` |
| `verified` | `int` | 0/1 — set by admin after manual review |
| `needs_reverify` | `tinyint(1)` | flags an account for re-verification |
| `voted`, `vote`, `pending_vote`, `voted_for` | `int` | CR-voting state for this user |
| `is_cr`, `cr_section`, `cr_course` | | set once a user becomes a CR |
| `is_candidate` | `tinyint` | 0/1 — standing as a CR candidate |
| `course`, `section` | `varchar` | |
| `reset_token`, `reset_expires` | | forgot-password flow |

### `user_details`
Extended profile submitted in `verify-form.php`, one row per user.
| Column | Type | Notes |
|---|---|---|
| `id` | `int` PK | |
| `user_id` | `int` | **UNIQUE** — one profile per user |
| `full_name`, `age`, `gender` | | |
| `roll_no`, `erp_id` | `varchar` | |
| `user_photo` | `varchar(255)` | selfie filename in `uploads/` |
| `college_id_photo`, `college_id_back` | `varchar(255)` | ID card photos |
| `course`, `section` | | |

### `candidates`
Students standing for **CR (Class Representative)** elections.
| Column | Type | Notes |
|---|---|---|
| `id` | `int` PK | |
| `user_id` | `int` | references `users.id` |
| `name`, `course`, `section` | | |
| `votes`, `pending_votes` | `int` | vote tally |

### `events`
Fest/competition events (e.g. Dance, Singing) — separate from CR elections.
| Column | Type | Notes |
|---|---|---|
| `id` | `int` PK | |
| `name`, `fest_name` | `varchar` | `fest_name` defaults to `'Ullaash'` |
| `time_limit` | `int` | minutes, default `10` |
| `start_time`, `end_time` | `datetime` | |
| `status` | `varchar(20)` | default `'pending'` |
| `winner_id` | `int` | |

### `event_candidates`
Candidates contesting a specific `event`.
| Column | Type | Notes |
|---|---|---|
| `id` | `int` PK | |
| `event_id` | `int` FK → `events.id` | |
| `name`, `votes` | | |

### `event_votes`
One row per vote cast in an event.
| Column | Type | Notes |
|---|---|---|
| `id` | `int` PK | |
| `user_id` | `int` FK → `users.id` | |
| `event_id` | `int` FK → `events.id` | |
| `candidate_id` | `int` | references `event_candidates.id` |
| `voted_at` | `timestamp` | |

### `ir_candidates` / `ir_votes`
Candidates and votes for an **IR (Independent Representative)** election — a separate, weighted voting track alongside CR voting.
| Table | Key columns |
|---|---|
| `ir_candidates` | `user_id`, `name`, `votes`, `weightage_votes` (`decimal`) |
| `ir_votes` | `voter_id` (**UNIQUE** — one IR vote per user), `candidate_id`, `weightage` (default `5.00`) |

### `voting_settings`
Key-value config table controlling election state.
| Column | Type | Notes |
|---|---|---|
| `setting_name` | `varchar(100)` | **UNIQUE**, e.g. `cr_voting_status`, `ir_voting_end_time` |
| `setting_value` | `text` | |

### Entity Relationships

```mermaid
erDiagram
    users ||--o| user_details : "has profile"
    users ||--o{ candidates : "may become"
    users ||--o{ ir_candidates : "may become"
    users ||--o{ event_votes : "casts"
    events ||--o{ event_candidates : "has"
    events ||--o{ event_votes : "receives"
    event_candidates ||--o{ event_votes : "receives"
    ir_candidates ||--o{ ir_votes : "receives"
```

> ⚠️ **Privacy note:** if you export your own live database for backups, **never commit the data-filled export** — it contains real names, emails, phone numbers, and password hashes of registered students. Only the structure-only [`database.sql`](./database.sql) in this repo is safe to share publicly.

---

## 📁 Project Structure

```text
online-voting/
├── admin-dashboard.php     # Admin control panel
├── admin-login.php         # Admin authentication
├── admin-logout.php        # Admin session termination
├── clear-session.php       # Helper to clear pending session data
├── college-banner.png      # Branding asset
├── college-logo.jpg        # Branding asset
├── dashboard.php           # Student dashboard
├── db.php                  # Database connection config
├── edit-profile.php        # Student profile editing
├── event-result.php        # Election results (per event)
├── event-vote.php          # Voting page for a specific event
├── forgot-password.php     # Password recovery flow
├── index.php               # Entry point / landing page
├── ir-vote.php              # Independent representative voting
├── loading.php               # Reusable loading-screen overlay component
├── login.php                  # Student login
├── logout.php                 # Student session termination
├── register.php               # Student registration
├── result.php                 # Overall results page
├── style.css                  # Global stylesheet
├── verify-form.php            # Student profile completion (photo + details)
├── view-photo.php             # Admin view of a student's uploaded photo
└── vote.php                   # Voting page
```

> 📝 Some file purposes above are inferred from filenames — double-check these against your actual code/comments and adjust as needed.

---

## ⚙️ Setup & Installation

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (or any Apache + PHP + MySQL stack)
- PHP 7.4+
- A modern browser (for webcam selfie capture support)

### Steps

**1. Clone the repository** into your XAMPP `htdocs` folder:
```bash
git clone https://github.com/akhtarayan57/Online-Voting-System.git
cd Online-Voting-System
```
Or place it directly at `C:\xampp\htdocs\online-voting`.

**2. Start Apache & MySQL** from the XAMPP Control Panel.

**3. Create the database.**
Open [phpMyAdmin](http://localhost/phpmyadmin), create a database named `online-voting`, and import [`database.sql`](./database.sql) from this repo (structure only — you'll start with empty tables).

**4. Configure the database connection** in `db.php`:
```php
<?php
$conn = mysqli_connect("localhost", "root", "", "online-voting");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
```

**5. Set up the uploads folder** (stores student selfies):
```bash
mkdir uploads
```

**6. Visit the app** in your browser:
```
http://localhost/online-voting/
```

---

## 🚦 Usage Flow

1. **Student registers** via `register.php` (account created, unverified)
2. **Student logs in** via `login.php`
3. **Student completes their profile** via `verify-form.php` — personal info, college info, and a selfie photo
4. **Admin reviews the submission** (photo + details) from the admin dashboard and manually marks the student as **verified**
5. Once verified, the student can **cast their vote** and later **view results**

Admins access their own portal separately through `admin-login.php` → `admin-dashboard.php`.

---

## 🔒 Security Notes

- All database queries use **prepared statements** to prevent SQL injection
- Passwords are stored using `password_hash()` and checked with `password_verify()` — never stored in plain text
- Student identity verification is **manual (admin-reviewed)** rather than automated, to keep the election process trustworthy
- Uploaded photos are restricted to `.jpg`, `.jpeg`, and `.png` file types

> ⚠️ Before deploying beyond a local/college network, consider adding CSRF protection, rate-limiting on login/registration, and HTTPS.

---

## 🚧 Future Improvements

- [ ] Add CSRF tokens to all forms
- [ ] Email notifications when a profile is verified/rejected
- [ ] Pagination on the admin dashboard for large student lists
- [ ] Export results as PDF/CSV
- [ ] Dockerize for easier setup

---

## 👤 Author

**Ayan Akhtar**
GitHub: [@akhtarayan57](https://github.com/akhtarayan57)
Built as a college coursework project at SRMU.

---

## 📄 License

This project is for academic purposes. Feel free to fork and adapt it under the [MIT License](https://choosealicense.com/licenses/mit/).
