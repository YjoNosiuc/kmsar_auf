# KMSAR User Acceptance Test Suite

**System:** Knowledge Management System for Academic Research (KMSAR)  
**Institution:** Angeles University Foundation — OVPRI / CDAIC  
**Application URL:** `http://kmsar_auf.test`  
**Document type:** Formal UAT (manual + automated)  
**Version:** 2.0 — current production behavior  
**Automated run:** `npx playwright test tests/e2e/tc-uat-current-roles.spec.ts`

| Field | Value |
|-------|--------|
| Prepared for | Acceptance of the live KMSAR build |
| Environment | Laragon local / demo seed |
| Browser | Chrome or Edge (latest) |
| Seed command | `php artisan migrate:fresh --seed` |
| All seeded passwords | `password` |

**How to mark results:** `P` Pass · `F` Fail · `B` Blocked · `N/A` Not applicable  
**Severity if fail:** S1 Blocks go-live · S2 Major · S3 Minor / cosmetic

Do **not** use `migrate:fresh` in the middle of a signed-off run. Workflow cases that change data (endorse / return / reject / submit) should be run last, or after a fresh seed.

---

## 1. Purpose and scope

This suite accepts **every live role** against **every function the current build exposes**.

**In scope**

- Guest: login, register + email OTP, forgot/reset password
- Faculty: My Research, wizard, documents, submit, revise, progress update, delete draft, profile, notifications
- Viewer: read-only research
- Co-author: view / optional upload on shared records
- College dean (and unit head, same screens): dashboard, queue, endorse / return / reject, reports
- OVPRI and CDAIC (same screens): dashboard, queue, approve / return / reject, All Research, reports
- Super admin: dashboard, users (including pending approval), colleges/programs, reports, audit logs; Import Data hidden from sidebar
- Reports rule: **only OVPRI-approved research**; dates use **OVPRI approval date**; blank dates = all approved years
- Access control (403 / login redirect)
- Shared chrome: sidebar, profile, sign out, notification bell

**Out of scope (not in the current product)**

- Registrar role in User Management (role exists in Spatie; hidden / not seeded)
- Import Data in the admin sidebar (routes still exist if opened by URL)
- `dean.research` All Research link (route is not registered; do not fail the suite if the link is absent)

---

## 2. Accounts

| Role | Email | Home after login |
|------|--------|------------------|
| Super Admin | `admin@yopmail.com` | `/admin/dashboard` |
| OVPRI Admin | `ovpri@yopmail.com` | `/ovpri/dashboard` |
| CDAIC Admin | `cdaic@yopmail.com` | `/ovpri/dashboard` |
| CCS Dean | `dean.ccs@yopmail.com` | `/dean/dashboard` |
| CAMP Dean | `dean.camp@yopmail.com` | `/dean/dashboard` |
| CCS Faculty 1 (Maria Santos) | `faculty.ccs1@yopmail.com` | `/research` |
| CCS Faculty 2 (Juan Dela Cruz) | `faculty.ccs2@yopmail.com` | `/research` |
| CAMP Faculty 1 (Elena Cruz) | `faculty.camp1@yopmail.com` | `/research` |
| CAMP Faculty 2 (Paolo Reyes) | `faculty.camp2@yopmail.com` | `/research` |
| Viewer | Create via Admin → User Management, role Viewer | `/research` |
| Unit head | Same as dean if an account is created | `/dean/dashboard` |

---

## 3. Seeded research inventory (oracle)

Use these rows as the expected result set. Approval dates come from `ApprovalSeeder` (`approvals.acted_at`), not from `research.created_at`.

| Ref | Title (short) | College | Owner | Progress | Stage | In Reports? | OVPRI approved |
|-----|---------------|---------|-------|----------|-------|-------------|----------------|
| AUF-2025-CCS-0001 | Tagalog Sentiment | CCS | faculty.ccs1 | proposal | draft | No | — |
| AUF-2025-CCS-0002 | Crop Disease Detection | CCS | faculty.ccs1 | ongoing | dean_review | No | — |
| AUF-2025-CCS-0003 | Blockchain Credential | CCS | faculty.ccs1 | published_scopus | approved | **Yes** | 2025-03-28 |
| AUF-2025-CCS-0004 | Augmented Reality Anatomy | CCS | faculty.ccs2 | proposal | dean_review | No | — |
| AUF-2025-CCS-0005 | Federated Learning Medical | CCS | faculty.ccs2 | completed_unpublished | ovpri_review | No | — |
| AUF-2025-CCS-0006 | IoT Smart Campus | CCS | faculty.ccs2 | published_non_indexed | approved | **Yes** | 2025-05-15 |
| AUF-2025-CAMP-0001 | Home-Based PT | CAMP | faculty.camp1 | proposal | draft | No | — |
| AUF-2025-CAMP-0002 | Telerehabilitation | CAMP | faculty.camp1 | ongoing | ovpri_review | No | — |
| AUF-2025-CAMP-0003 | Ergonomic Interventions | CAMP | faculty.camp1 | presented_external | approved | **Yes** | 2025-02-20 |
| AUF-2025-CAMP-0004 | Laboratory Quality | CAMP | faculty.camp2 | completed_unpublished | dean_review | No | — |
| AUF-2025-CAMP-0005 | Antimicrobial Stewardship | CAMP | faculty.camp2 | published_scopus | approved | **Yes** | 2025-04-22 |
| AUF-2025-CAMP-0006 | Point-of-Care Testing | CAMP | faculty.camp2 | presented_internal | approved | **Yes** | 2025-03-12 |

**Reports expected counts (dates blank, default completed outputs):**

| Who | Expected rows |
|-----|----------------|
| Admin / OVPRI / CDAIC | 5 (CCS-0003, CCS-0006, CAMP-0003, CAMP-0005, CAMP-0006) |
| CCS Dean | 2 (CCS-0003, CCS-0006) |
| CAMP Dean | 3 (CAMP-0003, CAMP-0005, CAMP-0006) |
| Faculty / Viewer | 403 — no Reports |

---

## 4. Guest and authentication

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| AUTH-01 | P0 | Open `/` while logged out | Redirect to `/login`. Email/login and password fields visible. Submit button visible | ☐ |
| AUTH-02 | P0 | Submit empty login | Validation errors. Stay on login | ☐ |
| AUTH-03 | P0 | Valid email + wrong password | Error. Stay on login. No session | ☐ |
| AUTH-04 | P0 | Unknown email | Error. Stay on login | ☐ |
| AUTH-05 | P0 | Log in as each seeded role | Lands on the home URL in §2 | ☐ |
| AUTH-06 | P0 | Open `/research`, `/reports`, `/admin/dashboard`, `/ovpri/dashboard`, `/dean/dashboard` while logged out | Each redirects to `/login` | ☐ |
| AUTH-07 | P0 | Sign Out from any role | Session ends. `/login` | ☐ |
| AUTH-08 | P1 | Open `/register` | Registration form: name, user type, college, email, password, confirm | ☐ |
| AUTH-09 | P0 | Register a new AUF user and complete the 6-digit email OTP | After correct OTP, account is pending admin approval. Login shows pending-approval message, not the dashboard | ☐ |
| AUTH-10 | P1 | Wrong OTP | Error. Account not created / not activated | ☐ |
| AUTH-11 | P1 | Resend verification | New OTP sent. Previous code expires per current 1-minute rule | ☐ |
| AUTH-12 | P1 | Open Forgot password, request OTP, verify, set new password, log in | Password changes. Old password fails. New password works | ☐ |
| AUTH-13 | P2 | Inactive or pending user tries login | Blocked with a clear message. No dashboard | ☐ |

---

## 5. Shared chrome (every logged-in role)

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| SHR-01 | P0 | Open My Profile | Personal information form and change-password form | ☐ |
| SHR-02 | P1 | Update first/last name | Success flash. Name updates in header/sidebar | ☐ |
| SHR-03 | P1 | Change password (current + new + confirm) | Success. Re-login requires the new password | ☐ |
| SHR-04 | P1 | Change password with wrong current password | Validation error. Password unchanged | ☐ |
| SHR-05 | P1 | Open notification bell | Dropdown of recent notices. Unread badge if any | ☐ |
| SHR-06 | P1 | Open `/notifications`, mark one read, mark all read | Read state updates. Badge clears after mark-all | ☐ |
| SHR-07 | P2 | Sidebar active state | Current page is highlighted | ☐ |

---

## 6. Faculty — `faculty.ccs1@yopmail.com`

### 6.1 Navigation and list

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| FAC-01 | P0 | Log in | `/research`. Heading **My research** | ☐ |
| FAC-02 | P0 | Check sidebar | **My Research**, **Register New**, **My Profile**, **Sign Out**. No Reports, no Approval Queue, no User Management, no Import Data | ☐ |
| FAC-03 | P0 | List contents | Sees CCS-0001 (draft), CCS-0002 (dean review), CCS-0003 (approved). Does **not** see CAMP records or faculty.ccs2 titles | ☐ |
| FAC-04 | P0 | Search `Blockchain` → Apply | URL contains `search=`. Only Blockchain card. Other own records hidden | ☐ |
| FAC-05 | P0 | Approval stage = Draft | Only CCS-0001. Reset restores all three | ☐ |
| FAC-06 | P0 | Progress status = Ongoing | Only CCS-0002 | ☐ |
| FAC-07 | P0 | Search a title that exists only for the other faculty | Empty: **No results found**. Filters remain | ☐ |
| FAC-08 | P1 | Pagination (if >15 records after creating extras) | Page 2 keeps `search` / `approval_stage` / `status` in the URL | ☐ |
| FAC-09 | P0 | Click View on CCS-0003 | Detail page: title, authors, documents, badges, no 403 | ☐ |
| FAC-10 | P0 | Open `/reports` | **403** | ☐ |

### 6.2 Register, draft, documents, submit

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| FAC-11 | P0 | Register New | Creates a draft and opens Step 1 (details) | ☐ |
| FAC-12 | P0 | Save Step 1 with title, college, classification, SDG, dates, progress | Continues to Authors. Data persists on back | ☐ |
| FAC-13 | P0 | Reuse an existing title (e.g. Crop Disease…) | Duplicate-title block. Record not saved as a second original | ☐ |
| FAC-14 | P0 | Step 2: set self as primary author; add a CCS co-author via search | Co-author listed. Primary cannot be removed | ☐ |
| FAC-15 | P0 | Skip to documents without completing authors | Warning / forced back to incomplete step | ☐ |
| FAC-16 | P0 | Upload allowed file (PDF ≤100MB) | Stored; not a public URL. AV starts as pending then clean in demo | ☐ |
| FAC-17 | P0 | Upload disallowed type (e.g. `.txt` / `.exe`) | Rejected. No document row | ☐ |
| FAC-18 | P1 | Upload more than 10 files | Blocked at client or server | ☐ |
| FAC-19 | P0 | Submit with no clean required document | Submit blocked | ☐ |
| FAC-20 | P0 | Submit a complete draft | Stage → `dean_review`. Faculty cannot edit core fields. Dean of that college is notified | ☐ |
| FAC-21 | P0 | Delete on a **draft** you own | Confirm. Record gone from list | ☐ |
| FAC-22 | P0 | Delete on submitted/approved | Delete control absent or rejected | ☐ |
| FAC-23 | P1 | Log in as faculty.ccs2. Confirm the new record is absent unless they are co-author | Scope is own + co-authored only | ☐ |

### 6.3 Return, revise, progress

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| FAC-24 | P0 | After dean **return**, open the record | Returned to draft (or equivalent). Remarks visible. Faculty can edit and resubmit | ☐ |
| FAC-25 | P0 | After OVPRI **return** | Faculty can revise. `revision_count` increments on return | ☐ |
| FAC-26 | P0 | After **reject** | Record rejected. Cannot quietly resubmit as the same approved chain | ☐ |
| FAC-27 | P1 | On an approved record, update progress | New stage starts the approval chain again (dean → OVPRI) | ☐ |
| FAC-28 | P1 | Download / preview a clean document | File downloads via the app. Path is not `/storage/...` public | ☐ |
| FAC-29 | P2 | Co-author (ccs2 on a shared record) can view; edit only if `can_edit` | Matches author flags | ☐ |

### 6.4 Access denied

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| FAC-30 | P0 | `/dean/dashboard`, `/approval/queue`, `/ovpri/dashboard`, `/ovpri/queue`, `/ovpri/research`, `/admin/dashboard`, `/admin/users`, `/admin/colleges`, `/admin/audit-logs` | All **403** | ☐ |

---

## 7. Viewer

Create an active Viewer in User Management (or the automated suite creates a temporary one).

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| VIEW-01 | P0 | Log in | `/research`. Heading My research | ☐ |
| VIEW-02 | P0 | Sidebar | My Research, My Profile. **No Register New**. No Reports | ☐ |
| VIEW-03 | P0 | Open `/research/create` and `/reports` | 403 | ☐ |
| VIEW-04 | P1 | Open a research they do not own | 403 or empty list — never another faculty’s private drafts | ☐ |

---

## 8. College Dean — `dean.ccs@yopmail.com` (repeat CAMP with `dean.camp`)

### 8.1 Dashboard and queue

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| DEAN-01 | P0 | Log in | `/dean/dashboard` | ☐ |
| DEAN-02 | P0 | Sidebar | Dashboard, Approval Queue, Reports, My Profile. No User Management, no Import Data | ☐ |
| DEAN-03 | P0 | Dashboard cards | **Own college only.** Total = completed non-draft/non-rejected. In Progress = proposal+ongoing. Pending Endorsement = `dean_review` + `submitted_at` | ☐ |
| DEAN-04 | P0 | CCS seed check | Pending Endorsement includes Crop Disease and AR Anatomy. Does not include CAMP Telerehabilitation | ☐ |
| DEAN-05 | P1 | Date filter on dashboard | Filters by research **start date**. Cards update | ☐ |
| DEAN-06 | P1 | Research per faculty table | CCS faculty only. Search box filters names | ☐ |
| DEAN-07 | P0 | Approval Queue | CCS items in dean review only. No CAMP rows | ☐ |
| DEAN-08 | P0 | Open review for CCS-0002 | Full record, documents preview/download, Endorse / Return / Reject | ☐ |

### 8.2 Decisions (run last; changes seed)

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| DEAN-09 | P0 | Endorse with optional remarks | Stage → `ovpri_review`. Leaves dean queue. Appears on OVPRI queue. Faculty + OVPRI notified | ☐ |
| DEAN-10 | P0 | Return with empty remarks | Blocked. Remarks required (min 4 characters) | ☐ |
| DEAN-11 | P0 | Return with remarks | Stage → draft. Faculty sees remarks. `revision_count` +1 | ☐ |
| DEAN-12 | P0 | Reject with empty remarks | Blocked | ☐ |
| DEAN-13 | P0 | Reject with remarks | Stage → rejected. Faculty notified. Not in Reports | ☐ |
| DEAN-14 | P0 | Try to endorse a CAMP record by URL | 403 | ☐ |

### 8.3 Reports

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| DEAN-15 | P0 | Open Reports, dates blank | **OVPRI approved from/to** labels. Columns Registered + OVPRI approved. CCS: Blockchain + IoT only. Count **2** | ☐ |
| DEAN-16 | P0 | Confirm absent | No Tagalog draft, no Crop Disease, no Federated Learning, no CAMP titles | ☐ |
| DEAN-17 | P0 | Date from 2025-03-01 to 2025-03-31 | Only CCS-0003 (approved 28 Mar 2025). IoT (15 May) excluded | ☐ |
| DEAN-18 | P0 | Faculty filter = Maria Santos | Only her OVPRI-approved row(s) | ☐ |
| DEAN-19 | P1 | Classification / SDG / progress filters | Preview and Total Research card follow the filter | ☐ |
| DEAN-20 | P0 | Export PDF and Excel | Download. Same rows as preview. Includes Registered and OVPRI approved columns | ☐ |
| DEAN-21 | P0 | CAMP dean reports | Ergonomic, Antimicrobial, Point-of-Care only. Count **3**. No CCS titles | ☐ |

### 8.4 Access denied

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| DEAN-22 | P0 | `/research`, `/research/create`, `/ovpri/dashboard`, `/ovpri/queue`, `/admin/dashboard`, `/admin/users` | 403 | ☐ |

Unit head: same cases as dean, scoped to that user’s college.

---

## 9. OVPRI — `ovpri@yopmail.com` (repeat CDAIC)

### 9.1 Dashboard and All Research

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| OVP-01 | P0 | Log in | `/ovpri/dashboard` | ☐ |
| OVP-02 | P0 | Sidebar | Dashboard, Approval Queue, Reports, All Research, My Profile | ☐ |
| OVP-03 | P0 | Cards | University-wide. Total = completed. In Progress = proposal+ongoing. **Pending OVPRI approval** = `ovpri_review` + submitted only (seed: Federated Learning + Telerehabilitation = **2**). Published and Scopus overlap; they do not add up to Total | ☐ |
| OVP-04 | P1 | Charts | By college, Scopus, presented, classification, SDG, engagement. College filter/search if present | ☐ |
| OVP-05 | P0 | Approval Queue | CCS-0005 and CAMP-0002. No dean-review-only items | ☐ |
| OVP-06 | P0 | Open review CCS-0005 | Full record. Approve / Return / Reject. Documents available | ☐ |
| OVP-07 | P0 | All Research | University list. Filter college / stage / progress. Drafts excluded from this list | ☐ |

### 9.2 Decisions (run last)

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| OVP-08 | P0 | Approve | Stage → `approved`. `approvals.acted_at` is the Reports date. Faculty + dean notified | ☐ |
| OVP-09 | P0 | Return, empty remarks | Blocked (min 4) | ☐ |
| OVP-10 | P0 | Return with remarks | Faculty can revise. Dean informed | ☐ |
| OVP-11 | P0 | Reject, empty remarks | Blocked | ☐ |
| OVP-12 | P0 | Reject with remarks | Rejected. Faculty + dean notified. Not in Reports | ☐ |

### 9.3 Reports

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| OVP-13 | P0 | Reports, dates blank | All **5** approved titles. None of the 7 non-approved titles. Cards: Total 5; Scopus/WoS uses indexed flag or `published_scopus` | ☐ |
| OVP-14 | P0 | College = CCS | Only Blockchain + IoT | ☐ |
| OVP-15 | P0 | Dates 2025-03-01 to 2025-03-31 | CCS-0003 (28 Mar) and CAMP-0006 (12 Mar) only | ☐ |
| OVP-16 | P1 | SDG 4 | Subset of the 5 that tag SDG 4 | ☐ |
| OVP-17 | P0 | Excel + PDF export | Downloads. Filter summary mentions OVPRI approved dates when set | ☐ |
| OVP-18 | P0 | Compare Admin vs OVPRI vs CDAIC on the same filters | **Identical row set** | ☐ |

### 9.4 Access denied

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| OVP-19 | P0 | `/research`, `/dean/dashboard`, `/approval/queue`, `/admin/dashboard`, `/admin/users` | 403 | ☐ |

CDAIC: OVP-01, OVP-13, OVP-18, OVP-19 must pass with `cdaic@yopmail.com`.

---

## 10. Super Admin — `admin@yopmail.com`

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| ADM-01 | P0 | Log in | `/admin/dashboard` | ☐ |
| ADM-02 | P0 | Sidebar | Dashboard, User Management, Colleges/Offices, Reports, Audit Logs, My Profile. **Import Data is not in the sidebar** | ☐ |
| ADM-03 | P0 | Dashboard cards | Total users, colleges, Total research (completed), In Progress, Pending approvals = dean_review **+** ovpri_review with `submitted_at` (seed **5**) | ☐ |
| ADM-04 | P1 | Dashboard charts | College, SDG, classification, approval-stage cards (no draft card). Date filter uses **start date** | ☐ |
| ADM-05 | P0 | User Management | Seeded users listed. Registrar accounts not shown. Pending-approval panel for self-registered users | ☐ |
| ADM-06 | P0 | Create user (faculty) | Appears in list. Can log in | ☐ |
| ADM-07 | P0 | Edit user (name/role/college/active) | Saves. Inactive user cannot log in | ☐ |
| ADM-08 | P0 | Approve a pending registration; assign role Faculty | User can log in to My Research | ☐ |
| ADM-09 | P0 | Reject a pending registration | User remains blocked | ☐ |
| ADM-10 | P0 | Colleges/Offices | CAMP, CAS, CBA, CCS, CCJE, CED, CEA and their official programs | ☐ |
| ADM-11 | P1 | Add / edit / deactivate college; add / edit / delete program | Changes persist. Inactive college excluded from new registration dropdowns | ☐ |
| ADM-12 | P0 | Audit Logs | Entries for login / approval / user changes. Filterable. Append-only (no edit/delete) | ☐ |
| ADM-13 | P0 | Reports | Same **5** OVPRI-approved rows as OVPRI. Same date labels and columns | ☐ |
| ADM-14 | P0 | Reports date range Mar 2025 | Same two rows as OVP-15 | ☐ |
| ADM-15 | P1 | Direct URL `/admin/import/users` | Page may load; **must not** appear in the sidebar | ☐ |
| ADM-16 | P2 | If import is used anyway | Users then research; skip rows reported; no crash | ☐ |

---

## 11. Cross-role happy path (one new record)

Use a unique title, e.g. `UAT PATH <timestamp>`.

| ID | Pri | Steps | Expected | Result |
|----|-----|-------|----------|--------|
| E2E-01 | P0 | Faculty registers, authors, uploads clean PDF, submits | Dean queue of that college | ☐ |
| E2E-02 | P0 | Faculty list search finds it on any page | Server-side filter | ☐ |
| E2E-03 | P0 | Dean endorses | OVPRI queue. Not yet in Reports | ☐ |
| E2E-04 | P0 | OVPRI approves | Faculty notified. Reports (all years) now include it. **OVPRI approved** date = approve time | ☐ |
| E2E-05 | P0 | Admin / OVPRI / that college’s dean open Reports | All three see the new row. Other college dean does not | ☐ |
| E2E-06 | P0 | Faculty / viewer open `/reports` | Still 403 | ☐ |

---

## 12. Access-control matrix

| Path | Guest | Faculty | Viewer | Dean | OVPRI/CDAIC | Admin |
|------|-------|---------|--------|------|-------------|-------|
| `/login` | Yes | — | — | — | — | — |
| `/research` | Login | Yes | Yes | 403 | 403 | Yes* |
| `/research/create` | Login | Yes | 403 | 403 | 403 | Yes* |
| `/reports` | Login | 403 | 403 | Yes (college, OVPRI-approved) | Yes (all, OVPRI-approved) | Yes (all, OVPRI-approved) |
| `/dean/dashboard` | Login | 403 | 403 | Yes | 403 | Yes* |
| `/approval/queue` | Login | 403 | 403 | Yes | 403 | Yes* |
| `/ovpri/dashboard` | Login | 403 | 403 | 403 | Yes | Yes* |
| `/ovpri/queue` | Login | 403 | 403 | 403 | Yes | Yes* |
| `/admin/dashboard` | Login | 403 | 403 | 403 | 403 | Yes |
| `/admin/users` | Login | 403 | 403 | 403 | 403 | Yes |
| `/admin/audit-logs` | Login | 403 | 403 | 403 | 403 | Yes |

\*Admin route middleware allows some of these URLs; UAT still requires the **sidebar** to show only Administration items.

---

## 13. Sign-off

| Role / area | Cases run | P | F | B | Tester | Date |
|-------------|-----------|---|---|---|--------|------|
| Auth / guest | AUTH-01–13 | | | | | |
| Shared chrome | SHR-01–07 | | | | | |
| Faculty | FAC-01–30 | | | | | |
| Viewer | VIEW-01–04 | | | | | |
| CCS Dean | DEAN-01–22 | | | | | |
| CAMP Dean | DEAN-15/21 | | | | | |
| OVPRI | OVP-01–19 | | | | | |
| CDAIC | OVP-01/13/18/19 | | | | | |
| Super Admin | ADM-01–16 | | | | | |
| End-to-end | E2E-01–06 | | | | | |

**Go-live recommendation:** ☐ Accept  ☐ Accept with S3 only  ☐ Reject  

**Notes:**

_________________________________________________________________

**Tester:** _________________ **Owner / faculty adviser:** _________________  
**Build / commit:** _________________ **URL:** `http://kmsar_auf.test`
