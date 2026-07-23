/**
 * Generates Excel fixtures for Import Data E2E tests.
 * Structure MUST match UserImport / ResearchImport:
 *   Row 1 = headers (WithHeadingRow default)
 *   Row 2 = instructions (skipped)
 *   Row 3+ = data (WithStartRow = 3)
 *
 * Run: npx tsx tests/e2e/fixtures/create-test-imports.ts
 */
import * as fs from 'fs';
import * as path from 'path';
import { fileURLToPath } from 'url';
import * as XLSX from 'xlsx';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = __dirname;

function writeSheet(filename: string, rows: (string | number)[][]): void {
  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.aoa_to_sheet(rows);
  XLSX.utils.book_append_sheet(wb, ws, 'Import');
  const outPath = path.join(OUT, filename);
  XLSX.writeFile(wb, outPath);
  console.log('Wrote', outPath);
}

const USER_HEADERS = [
  'name',
  'email',
  'employee_number',
  'college_code',
  'office',
  'role',
  'password',
];

const USER_INSTRUCTIONS = [
  'REQUIRED. Full name (UPPERCASE)',
  'REQUIRED. Unique email',
  'REQUIRED. Unique employee number',
  'REQUIRED. Active college code e.g. CCS',
  'OPTIONAL. Office name',
  'OPTIONAL. Role (default faculty)',
  'OPTIONAL. Blank = password',
];

const RESEARCH_HEADERS = [
  'registration_type',
  'title',
  'primary_author_email',
  'mother_college_code',
  'other_college_codes',
  'research_classification',
  'funding_agency',
  'sdg_tags',
  'expected_output',
  'expected_output_other',
  'start_date',
  'estimated_completion_date',
  'status',
  'approval_stage',
  'is_scopus_indexed',
  'coauthor_emails',
  'coauthor_can_edit',
];

const RESEARCH_INSTRUCTIONS = [
  'REQUIRED. new|update',
  'REQUIRED. Unique title',
  'REQUIRED. Existing user email',
  'REQUIRED. College code',
  'OPTIONAL. Pipe-separated codes',
  'REQUIRED. Classification snake_case',
  'OPTIONAL',
  'REQUIRED. e.g. 3|9',
  'REQUIRED. e.g. publication',
  'OPTIONAL unless other',
  'REQUIRED. YYYY-MM-DD',
  'REQUIRED. YYYY-MM-DD',
  'REQUIRED. status snake_case',
  'OPTIONAL. default approved',
  'OPTIONAL. 0|1',
  'OPTIONAL. Pipe-separated co-author emails',
  'OPTIONAL. Pipe-separated 0|1 matching coauthors (default 1)',
];

const TITLE_1 = 'TEST RESEARCH MACHINE LEARNING FOR CROP DISEASE DETECTION';
const TITLE_2 = 'TEST RESEARCH BLOCKCHAIN CREDENTIAL VERIFICATION SYSTEM';
const TITLE_TWO_CO = 'TEST RESEARCH WITH TWO COAUTHORS MACHINE LEARNING';
const TITLE_ONE_CO = 'TEST RESEARCH WITH ONE COAUTHOR VIEW ONLY';
const TITLE_NO_CO = 'TEST RESEARCH WITH NO COAUTHORS';
const TITLE_INVALID_CO = 'TEST RESEARCH INVALID COAUTHOR MACHINE LEARNING';

function researchRow(
  values: (string | number)[],
): (string | number)[] {
  // Pad to full header width when older fixtures omit coauthor columns
  while (values.length < RESEARCH_HEADERS.length) {
    values.push('');
  }
  return values;
}

// --- User fixtures ---

writeSheet('user_import_valid.xlsx', [
  USER_HEADERS,
  USER_INSTRUCTIONS,
  ['TEST FACULTY ONE', 'testfaculty1@auf.edu.ph', 'TEST-001', 'CCS', '', 'faculty', ''],
  ['TEST FACULTY TWO', 'testfaculty2@auf.edu.ph', 'TEST-002', 'CBA', 'OVPRI', 'faculty', ''],
  ['TEST FACULTY THREE', 'testfaculty3@auf.edu.ph', 'TEST-003', 'CEA', '', 'co_author', ''],
]);

writeSheet('user_import_duplicate.xlsx', [
  USER_HEADERS,
  USER_INSTRUCTIONS,
  ['TEST FACULTY DUPLICATE', 'testfaculty1@auf.edu.ph', 'TEST-099', 'CCS', '', 'faculty', ''],
]);

writeSheet('user_import_invalid_college.xlsx', [
  USER_HEADERS,
  USER_INSTRUCTIONS,
  ['TEST INVALID COLLEGE', 'testinvalidcollege@auf.edu.ph', 'TEST-INV', 'INVALID', '', 'faculty', ''],
]);

// --- Research fixtures ---

writeSheet('research_import_valid.xlsx', [
  RESEARCH_HEADERS,
  RESEARCH_INSTRUCTIONS,
  researchRow([
    'new',
    TITLE_1,
    'testfaculty1@auf.edu.ph',
    'CCS',
    '',
    'internally_funded',
    'DOST',
    '3|9',
    'publication',
    '',
    '2024-06-01',
    '2025-06-30',
    'published_scopus',
    'approved',
    1,
  ]),
  researchRow([
    'new',
    TITLE_2,
    'testfaculty2@auf.edu.ph',
    'CBA',
    '',
    'self_funded',
    '',
    '4|8',
    'publication',
    '',
    '2024-07-01',
    '2025-07-31',
    'ongoing',
    'approved',
    0,
  ]),
]);

writeSheet('research_import_duplicate.xlsx', [
  RESEARCH_HEADERS,
  RESEARCH_INSTRUCTIONS,
  researchRow([
    'new',
    TITLE_1,
    'testfaculty1@auf.edu.ph',
    'CCS',
    '',
    'internally_funded',
    'DOST',
    '3|9',
    'publication',
    '',
    '2024-06-01',
    '2025-06-30',
    'published_scopus',
    'approved',
    1,
  ]),
]);

writeSheet('research_import_missing_user.xlsx', [
  RESEARCH_HEADERS,
  RESEARCH_INSTRUCTIONS,
  researchRow([
    'new',
    'TEST RESEARCH MISSING AUTHOR RECORD',
    'nonexistent@auf.edu.ph',
    'CCS',
    '',
    'internally_funded',
    '',
    '3|9',
    'publication',
    '',
    '2024-06-01',
    '2025-06-30',
    'proposal',
    'approved',
    0,
  ]),
]);

writeSheet('research_import_with_coauthors.xlsx', [
  RESEARCH_HEADERS,
  RESEARCH_INSTRUCTIONS,
  researchRow([
    'new',
    TITLE_TWO_CO,
    'testfaculty1@auf.edu.ph',
    'CCS',
    '',
    'internally_funded',
    '',
    '3|9',
    'publication',
    '',
    '2024-06-01',
    '2025-06-30',
    'published_scopus',
    'approved',
    1,
    'testfaculty2@auf.edu.ph|testfaculty3@auf.edu.ph',
    '1|1',
  ]),
  researchRow([
    'new',
    TITLE_ONE_CO,
    'testfaculty2@auf.edu.ph',
    'CCS',
    '',
    'self_funded',
    '',
    '4',
    'publication',
    '',
    '2024-01-01',
    '2025-01-01',
    'ongoing',
    'approved',
    0,
    'testfaculty1@auf.edu.ph',
    '0',
  ]),
  researchRow([
    'new',
    TITLE_NO_CO,
    'testfaculty3@auf.edu.ph',
    'CCS',
    '',
    'externally_funded',
    'DOST',
    '9',
    'publication',
    '',
    '2024-03-01',
    '2025-03-01',
    'ongoing',
    'approved',
    0,
    '',
    '',
  ]),
]);

writeSheet('research_import_invalid_coauthor.xlsx', [
  RESEARCH_HEADERS,
  RESEARCH_INSTRUCTIONS,
  researchRow([
    'new',
    TITLE_INVALID_CO,
    'testfaculty1@auf.edu.ph',
    'CCS',
    '',
    'internally_funded',
    '',
    '3|9',
    'publication',
    '',
    '2024-06-01',
    '2025-06-30',
    'published_scopus',
    'approved',
    1,
    'nonexistent@auf.edu.ph',
    '1',
  ]),
]);

fs.writeFileSync(
  path.join(OUT, 'import-titles.json'),
  JSON.stringify(
    { TITLE_1, TITLE_2, TITLE_TWO_CO, TITLE_ONE_CO, TITLE_NO_CO, TITLE_INVALID_CO },
    null,
    2,
  ),
);

console.log('All import fixtures generated.');
