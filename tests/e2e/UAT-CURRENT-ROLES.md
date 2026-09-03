# KMSAR User Acceptance Test Suite

**System:** Knowledge Management System for Academic Research (KMSAR)  
**Institution:** Angeles University Foundation — OVPRI / CDAIC  
**Application URL:** `http://kmsar_auf.test`  
**Document type:** Formal UAT (manual + optional automated smoke)  
**Version:** 3.0 — dual-cycle workflow (updated build)  
**Automated smoke (partial):** `npx playwright test tests/e2e/tc-uat-current-roles.spec.ts`

| Field | Value |
|-------|--------|
| Prepared for | Acceptance of the current KMSAR build |
| Environment | Laragon local / demo seed |
| Browser | Chrome or Edge (latest) |
| Seed command | `php artisan migrate:fresh --seed` |
| Queue (email/notifications) | `php artisan queue:work --sleep=3 --tries=3` |
| All seeded passwords | `password` |

**How to mark results:** `P` Pass · `F` Fail · `B` Blocked · `N/A` Not applicable  
**Severity if fail:** S1 Blocks go-live · S2 Major · S3 Minor / cosmetic

Do **not** run `migrate:fresh` in the middle of a signed-off UAT run. Workflow cases that mutate data (submit, endorse, approve, return) should be executed **last**, or on a fresh seed dedicated to those cases.

---

## 1. Purpose and scope

This suite validates **every live role** against **every function the current build exposes**, using the **dual-cycle approval workflow**:

| Cycle | Purpose | Dean action | OVPRI action | Success status |
|-------|---------|-------------|--------------|----------------|
| **Initial** | Registration / intake | Endorse | Approve → **Research registered** | `research_registered` |
| **Final** | Completion / outcomes | Endorse | Approve → **Research accepted** | `research_accepted` |

**Return** (not reject) sends research back to faculty with required remarks. Initial return → `initial_rejected`. Final return → `final_rejected`.

### In scope

- Guest: login, register + email OTP, forgot / reset password
- Faculty: My Research, registration wizard, documents, submit, revise, completion update, co-authors, profile, notifications
- Viewer: read-only research list (no register)
- Co-author access (faculty user flagged `can_edit` on a shared record)
- College dean & unit head: dashboard, approval queue (Initial / Final tabs), endorse / return, college-scoped reports
- OVPRI admin & CDAIC admin: dashboard, approval queue, approve / return, All Research, university reports
- Super admin: dashboard, users (pending approval), colleges/programs, reports, audit logs; import via direct URL only
- **Reporting rules (institutional):**
  - **Total Research** everywhere = count of `status = research_accepted` only
  - **Research In Progress** = `research_registered` only
  - Report date filters use **`research_accepted_at`** (labeled OVPRI approved / Research accepted)
  - Blank dates = all accepted years
  - Dean reports include **mother college + other college affiliations**
  - OVPRI reports list includes **Other college affiliations** column
- Access control (403 / login redirect)
- Shared chrome: sidebar, profile, sign out, notification bell

### Out of scope (not in current product UI)

- Registrar role login workflow (role exists; not seeded; blocked from reports)
- **Import Data** in admin sidebar (routes exist at `/admin/import/users` and `/admin/import/research`)
- Dean **All Research** sidebar link (`dean.research` route not registered — OVPRI has `/ovpri/research`)
- Separate `proposal` status (merged into **draft**)

---

## 2. Test accounts (seeded)

| Role | Email | Password | Home after login |
|------|--------|----------|------------------|
| Super Admin | `admin@yopmail.com` | `password` | `/admin/dashboard` |
| OVPRI Admin | `ovpri@yopmail.com` | `password` | `/ovpri/dashboard` |
| CDAIC Admin | `cdaic@yopmail.com` | `password` | `/ovpri/dashboard` |
| CCS Dean | `dean.ccs@yopmail.com` | `password` | `/dean/dashboard` |
| CAMP Dean | `dean.camp@yopmail.com` | `password` | `/dean/dashboard` |
| CCS Faculty 1 (Maria Santos) | `faculty.ccs1@yopmail.com` | `password` | `/research` |
| CCS Faculty 2 (Juan Dela Cruz) | `faculty.ccs2@yopmail.com` | `password` | `/research` |
| CAMP Faculty 1 (Elena Cruz) | `faculty.camp1@yopmail.com` | `password` | `/research` |
| CAMP Faculty 2 (Paolo Reyes) | `faculty.camp2@yopmail.com` | `password` | `/research` |

**Create during UAT if needed**

| Role | How to create | Expected home |
|------|---------------|---------------|
| Viewer | Admin → User Management → role Viewer | `/research` (no Register New) |
| Unit head | Admin → User Management → role Unit Head + college | `/dean/dashboard` (same as dean) |
| Pending faculty | Self-register via `/register` | Blocked until admin approves |

---

## 3. Seeded research inventory (test oracle)

Use after `migrate:fresh --seed`. **Reports and Total Research counts use `research_accepted` only.**

| Ref | Title (short) | College | Owner | Workflow status | In reports? | `research_accepted_at` |
|-----|---------------|---------|-------|-----------------|-------------|--------------------------|
| AUF-2025-CCS-0001 | Tagalog Sentiment | CCS | ccs1 | Draft | No | — |
| AUF-2025-CCS-0002 | Crop Disease Detection | CCS | ccs1 | Initial dean review | No | — |
| AUF-2025-CCS-0003 | Blockchain Credential | CCS | ccs1 | **Research accepted** | **Yes** | 2025-04-12 |
| AUF-2025-CCS-0004 | Augmented Reality Anatomy | CCS | ccs2 | Initial dean review | No | — |
| AUF-2025-CCS-0005 | Federated Learning Medical | CCS | ccs2 | Final OVPRI review | No | — |
| AUF-2025-CCS-0006 | IoT Smart Campus | CCS | ccs2 | **Research accepted** | **Yes** | 2025-05-28 |
| AUF-2025-CCS-0007 | Mobile Learning Analytics | CCS | ccs1 | Research registered | No | — |
| AUF-2025-CAMP-0001 | Home-Based PT | CAMP | camp1 | Draft | No | — |
| AUF-2025-CAMP-0002 | Telerehabilitation | CAMP | camp1 | Initial OVPRI review | No | — |
| AUF-2025-CAMP-0003 | Ergonomic Interventions | CAMP | camp1 | **Research accepted** | **Yes** | 2025-03-01 |
| AUF-2025-CAMP-0004 | Laboratory Quality | CAMP | camp2 | Final dean review | No | — |
| AUF-2025-CAMP-0005 | Antimicrobial Stewardship | CAMP | camp2 | **Research accepted** | **Yes** | 2025-05-05 |
| AUF-2025-CAMP-0006 | Point-of-Care Testing | CAMP | camp2 | **Research accepted** | **Yes** | 2025-03-25 |

### Expected institutional counts (fresh seed, no date filter)

| Metric | University | CCS Dean | CAMP Dean |
|--------|------------|----------|-----------|
| **Total Research** (`research_accepted`) | **5** | **2** | **3** |
| **Research In Progress** (`research_registered`) | **1** | **1** | **0** |
| **Pending endorsement** (initial + final dean review, submitted) | — | **2** (0002, 0004) | **1** (0004) |
| **Pending OVPRI** (initial + final OVPRI review) | **2** | **1** (0005) | **1** (0002) |

### Reports date-filter oracle

| Filter (`research_accepted_at`) | Expected rows |
|---------------------------------|---------------|
| 2025-03-01 → 2025-03-31 (university) | CAMP-0003 (Mar 1), CAMP-0006 (Mar 25) |
| 2025-04-01 → 2025-04-30 (university) | CCS-0003 (Apr 12) |
| 2025-05-01 → 2025-05-31 (university) | CCS-0006 (May 28), CAMP-0005 (May 5) |

---

## 4. Guest and authentication

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| AUTH-01 | P0 | Open `/` while logged out | Redirect to `/login` | ☐ |
| AUTH-02 | P0 | Submit empty login | Validation errors; stay on login | ☐ |
| AUTH-03 | P0 | Valid email + wrong password | Error; no session | ☐ |
| AUTH-04 | P0 | Unknown email | Error; stay on login | ☐ |
| AUTH-05 | P0 | Log in as each seeded role (§2) | Lands on home URL in §2 | ☐ |
| AUTH-06 | P0 | Open `/research`, `/reports`, `/admin/dashboard`, `/ovpri/dashboard`, `/dean/dashboard` while logged out | Each redirects to `/login` | ☐ |
| AUTH-07 | P0 | Sign Out from any role | Session ends; `/login` | ☐ |
| AUTH-08 | P1 | Open `/register` | Registration form with user type, college, program, email, password | ☐ |
| AUTH-09 | P0 | Register new AUF user; complete 6-digit email OTP | Account pending admin approval; login blocked with clear message | ☐ |
| AUTH-10 | P1 | Enter wrong OTP | Error; account not activated | ☐ |
| AUTH-11 | P1 | Resend verification OTP | New code sent | ☐ |
| AUTH-12 | P1 | Forgot password: request OTP → verify → reset → login | Left panel shows **KMSAR** (no “Reset” word). Password changes; new password works | ☐ |
| AUTH-13 | P2 | Inactive / pending user tries login | Blocked with message; no dashboard | ☐ |

---

## 5. Shared chrome (all logged-in roles)

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| SHR-01 | P0 | Open **My Profile** | Personal info + change password forms | ☐ |
| SHR-02 | P1 | Update name fields | Success flash; name updates in sidebar | ☐ |
| SHR-03 | P1 | Change password (current + new + confirm) | Success; re-login with new password | ☐ |
| SHR-04 | P1 | Wrong current password on change | Validation error | ☐ |
| SHR-05 | P1 | Notification bell | Dropdown list; unread badge when applicable | ☐ |
| SHR-06 | P1 | `/notifications` → mark one read → mark all read | Badge updates | ☐ |
| SHR-07 | P2 | Sidebar active state | Current page highlighted | ☐ |

---

## 6. Faculty — `faculty.ccs1@yopmail.com`

### 6.1 Navigation and list

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| FAC-01 | P0 | Log in | `/research`; heading **My research** | ☐ |
| FAC-02 | P0 | Sidebar | My Research, Register New, My Profile, Sign Out. No Reports / Approval / Admin | ☐ |
| FAC-03 | P0 | List contents (fresh seed) | Own records: 0001 draft, 0002 initial dean, 0007 registered, 0003 accepted. No CAMP titles | ☐ |
| FAC-04 | P0 | Search `Blockchain` → Apply | URL has `search=`; only Blockchain card | ☐ |
| FAC-05 | P0 | Workflow status = **Research accepted** | Only CCS-0003 | ☐ |
| FAC-06 | P0 | Workflow status = **Draft** | **Not in dropdown** (draft visible on cards only) | ☐ |
| FAC-07 | P0 | Search title owned by other faculty | Empty state; filters preserved | ☐ |
| FAC-08 | P1 | Pagination (if >15 own records) | Page 2 keeps query params | ☐ |
| FAC-09 | P0 | View CCS-0003 | Detail page loads; documents; no 403 | ☐ |
| FAC-10 | P0 | Open `/reports` | **403** | ☐ |

### 6.2 Registration wizard (new research)

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| FAC-11 | P0 | **Register New** → choose New or Existing | Wizard Step 1 (details) opens | ☐ |
| FAC-12 | P0 | Step 1: title, mother college, other affiliations, classification, agenda themes, SDG, dates | Saves; proceeds to Authors | ☐ |
| FAC-13 | P0 | Duplicate title (e.g. existing seeded title) | Blocked with duplicate-title message | ☐ |
| FAC-14 | P0 | Step 2: primary author + co-author via user search | Co-author listed; primary locked | ☐ |
| FAC-15 | P0 | Skip authors with incomplete data | Validation / forced back | ☐ |
| FAC-16 | P0 | Step 3: upload PDF (allowed type, ≤100MB) | Document stored; not public URL; AV pending → clean | ☐ |
| FAC-17 | P0 | Upload disallowed extension (`.txt`, `.exe`) | Rejected | ☐ |
| FAC-18 | P1 | Paste external link instead of file | Saved as link document | ☐ |
| FAC-19 | P0 | Submit without required clean document | Submit blocked | ☐ |
| FAC-20 | P0 | Submit complete draft | Status → **Initial dean review**; dean notified | ☐ |
| FAC-21 | P0 | Delete own **draft** research | Confirm; removed from list | ☐ |
| FAC-22 | P0 | Delete on submitted / accepted research | Control absent or blocked | ☐ |
| FAC-23 | P1 | Log in as faculty.ccs2 | New record absent unless co-author | ☐ |

### 6.3 Return, revise, completion cycle

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| FAC-24 | P0 | After dean **return** (initial) | Status **Initial review — returned**; editable; can delete own uploaded files; resubmit available | ☐ |
| FAC-25 | P0 | After OVPRI **return** (initial) | Same pattern for initial cycle | ☐ |
| FAC-26 | P0 | After dean/OVPRI **return** (final) | Status **Final review — returned**; can revise completion | ☐ |
| FAC-27 | P0 | On **Research registered**, submit completion + outcome | Status → **Research completed** → **Final dean review** | ☐ |
| FAC-28 | P1 | On **Research accepted**, update progress / outcomes | Allowed per policy; may re-enter completion workflow | ☐ |
| FAC-29 | P1 | Download / preview clean document | Served via app route; not direct `/storage/` path | ☐ |
| FAC-30 | P2 | Co-author with `can_edit` on shared record | Can upload; cannot delete primary author’s files | ☐ |

### 6.4 Access denied

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| FAC-31 | P0 | `/dean/dashboard`, `/approval/queue`, `/ovpri/dashboard`, `/ovpri/queue`, `/ovpri/research`, `/admin/dashboard`, `/admin/users` | All **403** | ☐ |

---

## 7. Viewer

Create active Viewer in User Management, or use automated suite.

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| VIEW-01 | P0 | Log in | `/research` | ☐ |
| VIEW-02 | P0 | Sidebar | My Research, My Profile. **No Register New** | ☐ |
| VIEW-03 | P0 | `/research/create`, `/reports` | **403** | ☐ |
| VIEW-04 | P1 | Cannot see other faculty private drafts | 403 or empty scoped list | ☐ |

---

## 8. College Dean — `dean.ccs@yopmail.com`

Repeat key report cases for `dean.camp@yopmail.com` (CAMP oracle in §3).

### 8.1 Dashboard

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| DEAN-01 | P0 | Log in | `/dean/dashboard` | ☐ |
| DEAN-02 | P0 | Sidebar | Dashboard, Approval Queue, Reports, My Profile. No All Research link | ☐ |
| DEAN-03 | P0 | Stat cards (fresh seed, no dates) | Total Research **2**; In Progress **1** (0007); Pending Endorsement **2** | ☐ |
| DEAN-04 | P0 | **Recent research** table | Shows **research accepted** only for college (+ affiliations); count matches Total Research | ☐ |
| DEAN-05 | P1 | Date filter **Research accepted from/to** | Filters by `research_accepted_at`; Total + Recent + charts align | ☐ |
| DEAN-06 | P1 | Research per faculty table | CCS faculty only; search filters rows | ☐ |
| DEAN-07 | P1 | After OVPRI final-approves new CCS research | Total Research increments **immediately** (no stale cache) | ☐ |

### 8.2 Approval queue

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| DEAN-08 | P0 | Open Approval Queue | **Initial** and **Final** cycle tabs | ☐ |
| DEAN-09 | P0 | Initial tab (fresh seed) | CCS-0002, CCS-0004. No CAMP rows | ☐ |
| DEAN-10 | P0 | Final tab (fresh seed) | CAMP-0004 absent; CCS has none; CAMP dean sees 0004 | ☐ |
| DEAN-11 | P0 | Open review for CCS-0002 | Endorse + Return; document preview/download | ☐ |
| DEAN-12 | P0 | Try CAMP record by direct URL | **403** | ☐ |

### 8.3 Decisions (run last — mutates data)

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| DEAN-13 | P0 | **Endorse** (initial) with optional remarks | → Initial OVPRI review; leaves dean pending tab | ☐ |
| DEAN-14 | P0 | **Endorse** (final) | → Final OVPRI review | ☐ |
| DEAN-15 | P0 | **Return** with empty remarks | Blocked (remarks required, min length) | ☐ |
| DEAN-16 | P0 | **Return** with remarks | → `initial_rejected` or `final_rejected`; faculty notified; revision count +1 | ☐ |

### 8.4 Reports

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| DEAN-17 | P0 | Reports, no filters | **2** rows: Blockchain + IoT. Columns: Registered, OVPRI approved, Workflow status | ☐ |
| DEAN-18 | P0 | Workflow status = **Research accepted** | Same **2** rows | ☐ |
| DEAN-19 | P0 | Confirm absent | No drafts, no dean-review-only, no CAMP titles | ☐ |
| DEAN-20 | P0 | Date 2025-03-01 → 2025-03-31 | **0** CCS rows (CCS accepted dates in Apr/May) | ☐ |
| DEAN-21 | P0 | Faculty filter = Maria Santos | Her accepted row(s) only | ☐ |
| DEAN-22 | P1 | **Research progress** filter (outcome code) | Subset of accepted records with that outcome | ☐ |
| DEAN-23 | P0 | Export PDF + Excel | Downloads; same filter set as preview | ☐ |
| DEAN-24 | P1 | Research with **other college affiliation** to CCS | Visible in CCS dean reports/dashboard if affiliated | ☐ |
| DEAN-25 | P0 | CAMP dean reports | **3** accepted CAMP rows; no CCS titles | ☐ |

### 8.5 Access denied

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| DEAN-26 | P0 | `/research`, `/ovpri/dashboard`, `/admin/users` | **403** | ☐ |

**Unit head:** repeat DEAN-01–26 scoped to assigned college.

---

## 9. OVPRI / CDAIC — `ovpri@yopmail.com` (repeat with `cdaic@yopmail.com`)

### 9.1 Dashboard and All Research

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| OVP-01 | P0 | Log in | `/ovpri/dashboard` | ☐ |
| OVP-02 | P0 | Sidebar | Dashboard, Approval Queue, Reports, **All Research**, My Profile | ☐ |
| OVP-03 | P0 | Stat cards (fresh seed) | Total Research **5**; In Progress **1**; Pending OVPRI **2** | ☐ |
| OVP-04 | P1 | Charts | By college, Scopus, presented, classification, SDG, agenda themes | ☐ |
| OVP-05 | P0 | Approval Queue → Initial tab | CAMP-0002 | ☐ |
| OVP-06 | P0 | Approval Queue → Final tab | CCS-0005 | ☐ |
| OVP-07 | P0 | Review CCS-0005 | Approve + Return; documents accessible | ☐ |
| OVP-08 | P0 | **All Research** (default) | Lists **research accepted** only (5 seeded). Drafts excluded | ☐ |
| OVP-09 | P1 | All Research filters | College, workflow status, review cycle filters work | ☐ |

### 9.2 Decisions (run last)

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| OVP-10 | P0 | **Approve** initial review | → **Research registered**; `research_registered_at` set | ☐ |
| OVP-11 | P0 | **Approve** final review | → **Research accepted**; `research_accepted_at` set; dean + faculty notified | ☐ |
| OVP-12 | P0 | **Return** empty remarks | Blocked | ☐ |
| OVP-13 | P0 | **Return** with remarks | Returned status; faculty (+ dean) notified; not in reports until re-accepted | ☐ |

### 9.3 Reports

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| OVP-14 | P0 | Reports, no filters | **5** accepted rows. **Other college affiliations** column present | ☐ |
| OVP-15 | P0 | College = CCS | Blockchain + IoT only | ☐ |
| OVP-16 | P0 | Dates 2025-03-01 → 2025-03-31 | Ergonomic + Point-of-Care only | ☐ |
| OVP-17 | P1 | Workflow status = Research registered | **0** rows (reports default to accepted unless workflow-only filter) | ☐ |
| OVP-18 | P1 | SDG filter | Correct subset | ☐ |
| OVP-19 | P0 | PDF + Excel export | Downloads; filter summary shows acceptance dates when set | ☐ |
| OVP-20 | P0 | Compare OVPRI vs CDAIC vs Admin same filters | **Identical row set** | ☐ |

### 9.4 Access denied

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| OVP-21 | P0 | `/research`, `/dean/dashboard`, `/approval/queue`, `/admin/users` | **403** | ☐ |

---

## 10. Super Admin — `admin@yopmail.com`

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| ADM-01 | P0 | Log in | `/admin/dashboard` | ☐ |
| ADM-02 | P0 | Sidebar | Dashboard, User Management, Colleges/Offices, Reports, Audit Logs, My Profile. **No Import in sidebar** | ☐ |
| ADM-03 | P0 | Dashboard cards | Total Research **5** (accepted); In Progress **1**; Pending approvals **5** dean+OVPRI reviews with `submitted_at` | ☐ |
| ADM-04 | P1 | Dashboard charts + date filter | Charts update; acceptance date filter on totals where labeled | ☐ |
| ADM-05 | P0 | User Management | Seeded users listed; pending self-registrations panel | ☐ |
| ADM-06 | P0 | Create faculty user | Can log in to My Research | ☐ |
| ADM-07 | P0 | Edit user (role / college / active) | Saves; inactive cannot log in | ☐ |
| ADM-08 | P0 | Approve pending registration | User activates; correct role applied | ☐ |
| ADM-09 | P0 | Reject pending registration | User remains blocked | ☐ |
| ADM-10 | P0 | Colleges/Offices | 7 colleges (CAMP, CAS, CBA, CCS, CCJE, CED, CEA) + official programs | ☐ |
| ADM-11 | P1 | Toggle college inactive; add/edit/delete program | Persists; inactive excluded from registration dropdowns | ☐ |
| ADM-12 | P0 | Audit Logs | Login / approval / user actions logged; filterable; no edit/delete | ☐ |
| ADM-13 | P0 | Reports | Same **5** accepted rows as OVPRI | ☐ |
| ADM-14 | P1 | Direct URL `/admin/import/users` and `/admin/import/research` | Pages load; sidebar still hides Import | ☐ |
| ADM-15 | P2 | Import users CSV (optional) | Success/skip summary; no crash | ☐ |

---

## 11. End-to-end dual-cycle path (run last)

Use unique title: `UAT DUAL CYCLE <timestamp>`.

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| E2E-01 | P0 | Faculty: register → authors → upload clean PDF → submit | **Initial dean review** | ☐ |
| E2E-02 | P0 | Dean (initial): endorse | **Initial OVPRI review** | ☐ |
| E2E-03 | P0 | OVPRI (initial): approve | **Research registered**; not yet in Total Research / reports | ☐ |
| E2E-04 | P0 | Faculty: submit completion + outcome classification | **Final dean review** | ☐ |
| E2E-05 | P0 | Dean (final): endorse | **Final OVPRI review** | ☐ |
| E2E-06 | P0 | OVPRI (final): approve | **Research accepted**; `research_accepted_at` = now | ☐ |
| E2E-07 | P0 | Admin + OVPRI + owning college dean open Reports | All see new row; other college dean does not | ☐ |
| E2E-08 | P0 | Dean dashboard Total Research + Recent research | Both include new row **immediately** | ☐ |
| E2E-09 | P0 | Faculty / viewer `/reports` | Still **403** | ☐ |

### Return path variant (optional)

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| E2E-10 | P1 | Dean returns at initial with remarks | Faculty edits; deletes returned document; resubmits | ☐ |
| E2E-11 | P1 | OVPRI returns at final with remarks | Faculty revises completion; re-enters final review chain | ☐ |

---

## 12. Access-control matrix

| Path | Guest | Faculty | Viewer | Dean / Unit | OVPRI / CDAIC | Admin |
|------|-------|---------|--------|-------------|---------------|-------|
| `/login` | Yes | — | — | — | — | — |
| `/research` | Login | Yes | Yes | 403 | 403 | Yes* |
| `/research/create` | Login | Yes | 403 | 403 | 403 | Yes* |
| `/reports` | Login | 403 | 403 | Yes (college accepted) | Yes (all accepted) | Yes |
| `/dean/dashboard` | Login | 403 | 403 | Yes | 403 | Yes* |
| `/approval/queue` | Login | 403 | 403 | Yes | 403 | Yes* |
| `/ovpri/dashboard` | Login | 403 | 403 | 403 | Yes | Yes* |
| `/ovpri/queue` | Login | 403 | 403 | 403 | Yes | Yes* |
| `/ovpri/research` | Login | 403 | 403 | 403 | Yes | Yes* |
| `/admin/dashboard` | Login | 403 | 403 | 403 | 403 | Yes |
| `/admin/users` | Login | 403 | 403 | 403 | 403 | Yes |
| `/admin/audit-logs` | Login | 403 | 403 | 403 | 403 | Yes |

\*Super admin may open some URLs by middleware; sidebar should still show Administration items only.

---

## 13. Sign-off sheet

| Role / area | Cases | P | F | B | Tester | Date |
|-------------|-------|---|---|---|--------|------|
| Auth / guest | AUTH-01–13 | | | | | |
| Shared chrome | SHR-01–07 | | | | | |
| Faculty | FAC-01–31 | | | | | |
| Viewer | VIEW-01–04 | | | | | |
| CCS Dean | DEAN-01–26 | | | | | |
| CAMP Dean | DEAN-17, 25 (+ spot checks) | | | | | |
| OVPRI | OVP-01–21 | | | | | |
| CDAIC | OVP-01, 14, 20, 21 | | | | | |
| Super Admin | ADM-01–15 | | | | | |
| Dual-cycle E2E | E2E-01–11 | | | | | |

**Go-live recommendation:** ☐ Accept · ☐ Accept with S3 only · ☐ Reject  

**Build / commit tested:** _________________  
**Environment URL:** _________________  
**Tester signature:** _________________ **Date:** _________________  
**Owner / adviser signature:** _________________ **Date:** _________________  

**Notes / defects:**

_________________________________________________________________

_________________________________________________________________
