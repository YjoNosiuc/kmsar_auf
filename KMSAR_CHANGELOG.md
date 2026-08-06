# KMSAR System Changelog

**Knowledge Management System for Academic Research**  
Angeles University Foundation · OVPRI / CDAIC / CCS / MISS

| Field | Detail |
|-------|--------|
| Document date | 6 August 2026 |
| Stack | Laravel 11 · PHP 8.3 · MySQL 8 · Blade · Alpine.js · Tailwind CSS · Playwright |
| Commits covered | 14 (from initial commit through latest feature work) |
| Scope | UAT bug fixes, new features, security, performance, tests, and database changes |

---

## Executive Summary

KMSAR progressed from an initial working baseline through a full UAT remediation cycle, a comprehensive Playwright E2E suite, and Super Admin bulk import capabilities (users, research, co-authors, programs, and document links). Dashboard cache invalidation issues that caused stale dean/OVPRI stats were identified and corrected.

**At a glance**

- **83** tracked files changed vs. first commit (~9,979 insertions / ~819 deletions)
- **14** git commits on `master`
- **258** Playwright E2E tests across **14** spec files
- **2** new migrations added during UAT/feature work
- Additional enhancements (program import, document URL import, IMPORT-026–033) present in the working tree and ready to commit

---

## 1. UAT Bug Fixes

Fixes are grouped by UAT severity codes where they were tracked (H = High, M = Medium, L = Low), plus related post-UAT fixes.

### High priority (H)

| ID | Issue | What was fixed | Primary files |
|----|--------|----------------|---------------|
| **H-01** | PDF documents could not be previewed inline | Inline PDF preview on faculty/OVPRI/approval review screens | `ResearchController.php`, `faculty/research/show.blade.php`, `ovpri/review.blade.php`, `approval/review.blade.php` |
| **H-02** | Stale browser/session cache after actions | `NoCacheHeaders` middleware applied so sensitive pages are not cached by the browser | `app/Http/Middleware/NoCacheHeaders.php`, `bootstrap/app.php` |
| **H-03** | Rejected research could not be revised; SDG tags not saving | Revise & Resubmit flow restored; SDG picker values persist on wizard and edit paths | `ApprovalController.php`, `ResearchController.php`, `sdg-picker.blade.php`, faculty research views |
| **H-04** | Faculty not notified on submit / reject | Submission confirmation and improved rejection notifications | `ResearchSubmissionConfirmed.php`, `ResearchRejected.php`, `ResearchController.php`, `ApprovalController.php` |
| **H-05** | OVPRI “all research” access / 403 / sidebar gaps | OVPRI all-research listing, authorization, and sidebar navigation corrected | `OvpriController.php`, `ResearchPolicy.php`, `ovpri/research/index.blade.php`, `sidebar.blade.php`, `routes/web.php` |

### Medium priority (M)

| ID | Issue | What was fixed | Primary files |
|----|--------|----------------|---------------|
| **M-03** | Duplicate research / authors not prevented consistently | System-wide duplicate prevention (title and related checks) | `ResearchController.php`, `Research.php`, `ResearchAuthor.php`, `ApprovalService.php`, `ResearchPolicy.php` |
| **M-04** | Registration fields / other college incomplete | Field refinements; **other colleges** as multi-select (JSON) | Registration/edit Blade views, `Research` model, migration (see §6) |
| **M-05** | Reports incomplete | Filters, pagination, Research Progress column, PDF filter summary | `ReportController.php`, `ResearchReportExport.php`, `reports/*.blade.php` |
| **M-06** | Dashboard filters unreliable | Academic year / filter behavior on dean and OVPRI dashboards | `DeanController.php`, `OvpriController.php`, dashboard Blade views |
| **M-07** | OVPRI/CDAIC views inconsistent; CDAIC blocked on actions | Unified OVPRI/CDAIC views; CDAIC can return/reject/approve without 403 | `OvpriController.php`, `ApprovalController.php`, queue/dashboard views |

### Low priority (L) and related polish

| ID | Issue | What was fixed | Primary files |
|----|--------|----------------|---------------|
| **L-01** | Unclear upload success feedback | Clearer document upload success messaging | `DocumentController.php`, documents Blade |
| **L-02** | Timezone not Philippine local | App timezone set to **Asia/Manila** | `config/app.php` |
| **L-03** | Modals not scrollable | Scrollable modal content for long forms | Admin users / related UI |
| **L-04** | Active college count inaccurate | Correct active college counting | Admin users / related controllers |
| — | OVPRI queue UX | Queue sort and college filter | `ovpri/queue.blade.php`, `ApprovalController.php` |
| — | File naming | Standardized stored/original filename handling on upload | `DocumentController.php` |
| — | Admin dashboard | Research status breakdown cards/stats | `admin/dashboard.blade.php` |
| — | Non-college office users | `office` support for users not tied only to a college | `UserController.php`, `User` model, migration (see §6) |
| — | Edit form other colleges | `selectedOtherColleges` correctly pre-selected on edit | `faculty/research/edit.blade.php` |

---

## 2. New Features Added

### 2.1 Super Admin — Bulk User Import

- **Route / UI:** `/admin/import/users` (sidebar: **Import Data**)
- **Implementation:** `UserImport.php`, `ImportController.php`, `admin/import/users.blade.php`
- **Capability:** Upload `.xlsx` (max 10 MB) to create faculty/staff accounts in bulk
- **Columns:** name, email, employee_number, college_code, **program_code** (optional), office, role, password
- **Behavior:** Skips duplicates (email / employee number) and invalid colleges; defaults role to `faculty` and password to `password`; reports imported vs skipped rows

### 2.2 Super Admin — Bulk Research Import

- **Route / UI:** `/admin/import/research`
- **Implementation:** `ResearchImport.php`, `admin/import/research.blade.php`
- **Capability:** Bulk-create research records with reference numbers (`AUF-{YEAR}-{COLLEGE}-{SEQ}`), primary author, SDGs, dates, status, approval stage, Scopus flag
- **Co-authors:** `coauthor_emails` and `coauthor_can_edit` (pipe-separated); missing co-author emails are logged without blocking the research row
- **Document links:** `document_url` and `document_label` (pipe-separated HTTPS links stored as external documents)
- **Cache:** Successful imports invalidate dean / OVPRI / admin dashboard caches

### 2.3 Program assignment on users

- **Import:** Optional `program_code` resolved within the user’s college (`UserImport`)
- **Admin UI API:** `program_id` validated and saved on user create/update (`Admin\UserController`)

### 2.4 Co-author edit permission enforcement

- `ResearchPolicy` now grants co-author edit access only when `can_edit = true` on the `research_authors` pivot/row

### 2.5 Other college multi-select

- Research may list multiple collaborating colleges; `other_college_id` stored as JSON

### 2.6 Notifications

- Faculty receive confirmation when a submission is sent for review
- Rejection notifications improved for clarity

### 2.7 Reporting enhancements

- Richer filters, pagination, Research Progress column, and PDF filter summary on college/OVPRI reports

---

## 3. Security Fixes

| Area | Description | Files |
|------|-------------|-------|
| Authorization (CDAIC) | CDAIC admins no longer receive incorrect 403 on return / reject / approve | `ApprovalController.php`, `OvpriController.php`, policies/services as needed |
| Co-author permissions | Co-authors without `can_edit` cannot edit via broad `research.update` permission | `ResearchPolicy.php` |
| Browser cache of private pages | `NoCacheHeaders` reduces risk of sensitive research data remaining in browser cache | `NoCacheHeaders.php`, `bootstrap/app.php` |
| Import access control | Import routes restricted to `super_admin` middleware group | `routes/web.php` |
| File storage model | Uploads remain under `storage/app/research_files/...` (not public web root); external import links stored as URLs only | `DocumentController.php`, `ResearchImport.php` |

---

## 4. Performance Fixes

| Issue | Fix | Files |
|-------|-----|-------|
| Dean dashboard stats stayed stale after submit/endorse/import | Cache keys now clear **all** academic-year variants (`_all_` and year segments), not only a single day key | `ResearchController.php`, `ApprovalController.php` |
| Research import left dashboards stale | After each successful imported research row, OVPRI, admin monthly, SDG, and dean caches are forgotten | `ResearchImport.php` |
| Browser showing outdated pages | Cache-Control headers via middleware | `NoCacheHeaders.php` |
| Earlier DB tuning | Performance indexes migration (users/research query paths) | `2026_04_04_000001_add_performance_indexes.php` |

**Verified by:** `tests/e2e/tc-cache.spec.ts` (CACHE-001 … CACHE-007)

---

## 5. Test Coverage

### Playwright E2E (14 files · **258** tests)

| Spec file | Tests | Focus |
|-----------|------:|-------|
| `tc-role-access.spec.ts` | 38 | Role matrix / unauthorized access |
| `tc-faculty.spec.ts` | 37 | Faculty research lifecycle UAT |
| `tc-import.spec.ts` | 33 | User & research import (incl. co-authors, programs, document URLs) |
| `tc-ovpri.spec.ts` | 33 | OVPRI/CDAIC review & dashboards |
| `tc-dean.spec.ts` | 29 | Dean endorsement & college scope |
| `tc-admin.spec.ts` | 28 | Super Admin management |
| `dean.spec.ts` | 11 | Dean smoke / regression |
| `ovpri.spec.ts` | 11 | OVPRI smoke / regression |
| `auth.spec.ts` | 9 | Login / session |
| `reports.spec.ts` | 7 | Report filters & exports |
| `tc-cache.spec.ts` | 7 | Dashboard cache invalidation |
| `faculty-research.spec.ts` | 6 | Faculty research smoke |
| `admin.spec.ts` | 5 | Admin smoke |
| `notifications.spec.ts` | 4 | In-app / mail notification flows |

**Helpers / fixtures:** `tests/e2e/helpers/{auth,db,research}.ts`, Excel fixtures under `tests/e2e/fixtures/`, generator `create-test-imports.ts`

**Notable milestones (from commits)**

- Complete UAT suite: **165/165** across roles (`675f6f6`)
- Import suite growth: 18 → 25 → **33** tests (users, research, co-authors, programs, documents)
- Cache suite: **7/7** passing

### Feature (PHPUnit) updates

- `tests/Feature/NotificationTest.php`
- `tests/Feature/ResearchLifecycleTest.php`

---

## 6. Database Changes

### Migrations added during post-baseline UAT / feature work

| Migration | Purpose |
|-----------|---------|
| `2026_06_19_085354_change_other_college_id_to_json_in_research_table.php` | Convert `other_college_id` from single FK to **JSON** for multi-college collaboration |
| `2026_06_20_004549_add_office_to_users_table.php` | Add nullable **`office`** on `users` for non-college office staff |

### Existing schema used by new features (no new migration required)

- `users.program_id` — already present; now populated via import and admin user forms
- `documents.external_link` — used for imported Drive/HTTPS document URLs
- Soft deletes, approvals, audit logs, Spatie roles — unchanged structurally

---

## 7. Files Modified

| Metric | Value |
|--------|------:|
| Files changed (first commit → `HEAD`) | **83** |
| Lines added (approx.) | **+9,979** |
| Lines removed (approx.) | **−819** |
| Git commits | **14** |

### Key new application files

| Path | Role |
|------|------|
| `app/Imports/UserImport.php` | Excel user import |
| `app/Imports/ResearchImport.php` | Excel research / co-author / document-link import |
| `app/Http/Controllers/Admin/ImportController.php` | Import UI + upload handling |
| `resources/views/admin/import/users.blade.php` | User import screen |
| `resources/views/admin/import/research.blade.php` | Research import screen |
| `app/Http/Middleware/NoCacheHeaders.php` | Anti-cache middleware |
| `app/Notifications/ResearchSubmissionConfirmed.php` | Faculty submit confirmation |
| `tests/e2e/tc-*.spec.ts` + helpers/fixtures | UAT / import / cache automation |

### Working tree (post-`HEAD`, not yet committed)

Includes program_code / document_url import support and IMPORT-026–033 coverage:

- `UserImport.php`, `UserController.php`, `ResearchImport.php`
- Updated Excel fixtures + `tc-import.spec.ts`
- New fixtures: `user_import_invalid_program.xlsx`, `research_import_with_documents.xlsx`

---

## 8. Commit Timeline

```
fd48a85  first commit
b4006e0  fix: H-01 PDF preview, H-02 session cache, H-05 OVPRI all research
9965888  fix: H-03 rejected revise + SDG saving
977a0f6  fix: H-04 submission confirmation & rejection notifications
cb79f3d  fix: M-03 duplicates, M-04 registration / other college multi-select
6f6250b  fix: M-05 reports filters, pagination, PDF summary
7ccff61  fix: M-06 dashboard filters, M-07 OVPRI/CDAIC + CDAIC 403
d6396d8  fix: L-01–L-04 + OVPRI queue sort/filter
2596676  fix: file naming, admin status breakdown, office users, edit colleges
675f6f6  test: Playwright UAT suite 165/165
bf57429  checkpoint: system stable before import
99ea71b  feat: user & research import + Playwright 18/18
9d12f5c  fix: dean/OVPRI cache keys + cache tests 7/7
1931c15  feat: co-author import, ResearchPolicy can_edit, import tests 25/25
```

---

## 9. How to Verify

```bash
# Full role UAT suite (historical milestone)
npx playwright test tests/e2e/tc-faculty.spec.ts tests/e2e/tc-dean.spec.ts \
  tests/e2e/tc-ovpri.spec.ts tests/e2e/tc-admin.spec.ts \
  tests/e2e/tc-role-access.spec.ts --reporter=list

# Import + cache
npx playwright test tests/e2e/tc-import.spec.ts tests/e2e/tc-cache.spec.ts --reporter=list
```

---

## Document control

| Version | Notes |
|---------|--------|
| 1.0 | Compiled from `git log`, `git diff --stat` (first commit → HEAD), and current working-tree import enhancements |

*Prepared for client / adviser review. For architecture detail, see `KMSAR_ARCHITECTURE.md`.*
