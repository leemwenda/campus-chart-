# KCA Chart — Campus Community Platform
## KCA University · PHP + MySQL · No Frameworks

---

## ✅ What's Built

### Authentication
- **Login:** Student ID **or** KCA email (`@students.kcau.ac.ke` / `@kcau.ac.ke`) + password
- **Register:** Student ID (required), email (optional), real KCA courses from dropdown, school/year/mode
- CSRF tokens, bcrypt passwords, session management, role-based access

### All Pages & Modules

| Module | URL | Description |
|--------|-----|-------------|
| Login | `/` (index.php) | Student ID OR email login with demo accounts |
| Register | `/pages/register.php` | Real KCA school/course dropdowns, Student ID validation |
| Home Feed | `/pages/feed.php` | Posts, likes, comments, pinned announcements |
| Spaces | `/pages/spaces.php` | Academic groups, clubs — join/leave |
| Events | `/pages/events.php` | Full 2026 academic calendar seeded, RSVP |
| Messages | `/pages/messages.php` | Private 1-on-1 chat with auto-polling |
| Members | `/pages/members.php` | Search, filter, follow, message |
| Notifications | `/pages/notifications.php` | Filter by type, mark all read |
| Profile | `/pages/profile.php` | Public profile view |
| Edit Profile | `/pages/profile_edit.php` | Bio, photo upload, course, password change |
| Admin Panel | `/admin/index.php` | Users, posts, events, spaces, announcements |
| Analytics | `/admin/analytics.php` | Stats dashboard with charts |

### Real KCA Data Included
- All 3 KCA schools (SoT, SoB, SEASS) + PTTI with all programmes
- 40+ clubs and academic spaces seeded as Spaces
- 30+ 2026 academic calendar events (all 3 trimesters) seeded into Events
- Real KCA campuses: Town (Nairobi), Western (Kisumu), Kitengela

### UI/UX
- **Desktop:** Fixed sidebar + topbar
- **Mobile:** Collapsible sidebar + **bottom navigation bar** (6 tabs)
- KCA colors: Navy `#003087`, Gold `#C9A84C`
- All icons are SVG (no Font Awesome needed)

---

## 🚀 Quick Setup (XAMPP)

### Step 1 — Copy Files
```
C:\xampp\htdocs\kcachart\
```

### Step 2 — Start XAMPP
Apache ✅ + MySQL ✅

### Step 3 — Import Database
1. Go to **http://localhost/phpmyadmin**
2. Create database: **`kcachart`**
3. Import **`config/database.sql`**

### Step 4 — Configure DB
Open `config/db.php` and set your credentials (default XAMPP has no password):
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'kcachart');
define('DB_USER', 'root');
define('DB_PASS', '');   // ← empty for default XAMPP
define('SITE_URL', 'http://localhost/kcachart');
```

### Step 5 — Open App
```
http://localhost/kcachart/
```

---

## 🔑 Demo Login Credentials

Login with **Student ID or email**, password is `password` for all accounts.

| Role    | Student ID      | Email                          |
|---------|-----------------|--------------------------------|
| Admin   | `ADMIN001`      | `admin@kca.ac.ke`              |
| Staff   | `KCA/2022/001`  | `j.wambua@kca.ac.ke`           |
| Student | `KCA/2023/045`  | `b.otieno@students.kca.ac.ke`  |
| Student | `KCA/2023/091`  | `a.mwangi@students.kca.ac.ke`  |

> ⚠️ Change all passwords after first login: Edit Profile → Change Password

---

## 🛠 Admin Panel Guide

Login as **ADMIN001** → sidebar → **Admin Panel**

| Tab | What you can do |
|-----|----------------|
| Dashboard | Platform stats at a glance |
| Users | Search users, change roles, disable accounts |
| Posts | Pin/unpin posts, delete posts |
| Events | Create events, delete events |
| Spaces | Create new spaces/communities |
| Announce | Send pinned announcement + notify all users |
| Analytics | Charts, post trends, space stats |

---

## 🌐 Production Deployment (Ubuntu/VPS/cPanel)

1. Upload files to `public_html/` or subdirectory
2. Create MySQL DB in cPanel
3. Update `config/db.php`:
   ```php
   define('DB_PASS', 'your_real_password');
   define('SITE_URL', 'https://yourdomain.com/kcachart');
   ```
4. Import `config/database.sql`
5. Make uploads writable:
   ```bash
   chmod 755 assets/uploads/
   chown www-data:www-data assets/uploads/
   ```

---

## 📁 File Structure

```
kcachart/
├── index.php              ← Login page
├── config/
│   ├── database.sql       ← Schema + all seed data
│   ├── db.php             ← PDO + DB helper
│   └── auth.php           ← Session, login, CSRF helpers
├── includes/
│   ├── header.php         ← Sidebar + topbar + mobile bottom nav
│   ├── footer.php         ← Footer JS
│   ├── kca_data.php       ← Real KCA schools/courses/clubs
│   └── space_card.php
├── pages/
│   ├── feed.php           ← Home Feed
│   ├── spaces.php         ← Spaces/Groups
│   ├── events.php         ← Events
│   ├── messages.php       ← Direct Messages
│   ├── members.php        ← Members Directory
│   ├── notifications.php  ← Notifications full page ✨ NEW
│   ├── profile.php        ← Profile view
│   ├── profile_edit.php   ← Edit profile + photo upload ✨ NEW
│   ├── post_create.php
│   ├── register.php
│   └── logout.php
├── admin/
│   ├── index.php          ← Admin Panel ✨ NEW
│   └── analytics.php
├── api/                   ← AJAX endpoints
│   ├── posts.php
│   ├── events.php
│   ├── spaces.php
│   ├── members.php
│   ├── messages.php
│   └── notifications.php
└── assets/
    ├── css/main.css       ← All styles
    ├── js/main.js         ← All JS (sidebar toggle, AJAX, etc.)
    ├── images/pattern.svg
    └── uploads/           ← Profile pictures (must be writable)
```

---

*KCA Chart v3.0 — Built for the KCA Family 🎓 · Nairobi, Kenya*
