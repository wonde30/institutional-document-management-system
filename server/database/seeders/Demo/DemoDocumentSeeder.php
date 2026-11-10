<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentCategory;
use App\Models\DocumentComment;
use App\Models\DocumentMovement;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\VersionFile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDocumentSeeder extends Seeder
{
    /**
     * Seed 75 realistic demonstration documents with physical files, versions, movements, and comments.
     */
    public function run(): void
    {
        $categories = DocumentCategory::pluck('id', 'code')->toArray();
        $confLevels = ConfidentialityLevel::pluck('id', 'code')->toArray();
        $units = OrganizationUnit::pluck('id', 'code')->toArray();
        $users = User::pluck('id', 'email')->toArray();

        $adminId = $users['demo.admin@example.test'] ?? 1;
        $recordsId = $users['demo.records@example.test'] ?? $adminId;
        $headCsId = $users['demo.head.cs@example.test'] ?? $adminId;
        $deanEngId = $users['demo.head.eng@example.test'] ?? $adminId;
        $approverId = $users['demo.approver@example.test'] ?? $adminId;
        $staffId = $users['demo.staff@example.test'] ?? $adminId;
        $hrId = $users['demo.hr@example.test'] ?? $adminId;
        $financeId = $users['demo.finance@example.test'] ?? $adminId;
        $researchId = $users['demo.research@example.test'] ?? $adminId;

        $catAcad = $categories['academic'] ?? 1;
        $catAdmin = $categories['administrative'] ?? 2;
        $catFin = $categories['finance'] ?? 3;
        $catHr = $categories['hr'] ?? 4;
        $catRes = $categories['research'] ?? 5;
        $catLeg = $categories['legal'] ?? 6;

        $confPub = $confLevels['public'] ?? 1;
        $confInt = $confLevels['internal'] ?? 2;
        $confConf = $confLevels['confidential'] ?? 3;
        $confRest = $confLevels['restricted'] ?? 4;

        $unitWu = $units['WU'] ?? 1;
        $unitVpaa = $units['VPAA'] ?? $unitWu;
        $unitCro = $units['CRO'] ?? $unitWu;
        $unitCote = $units['COTE'] ?? $unitWu;
        $unitCs = $units['CS'] ?? $unitWu;
        $unitEe = $units['EE'] ?? $unitWu;
        $unitCbbe = $units['CBBE'] ?? $unitWu;
        $unitHrmd = $units['HRMD'] ?? $unitWu;
        $unitFpd = $units['FPD'] ?? $unitWu;
        $unitRcsd = $units['RCSD'] ?? $unitWu;
        $unitQao = $units['QAO'] ?? $unitWu;

        $documentsCatalog = [
            // 1-10: Executive & Senate Records
            [
                'ref' => 'WU-DOC-2026-0001',
                'title' => 'Wollo University 48th Senate Regular Meeting Minutes and Resolutions',
                'summary' => 'Official minutes and ratified resolutions of the 48th Senate session covering academic calendar approvals, curriculum revisions, and honorary degrees.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $recordsId,
                'days_ago' => 15, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0002',
                'title' => 'Executive Directive on Kombolcha Campus Modernization Phase II',
                'summary' => 'Presidential directive outlining infrastructural upgrades, lab equipment procurement, and campus network fiber expansion.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $adminId,
                'days_ago' => 25, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0003',
                'title' => 'Revised Academic Policy Framework for Modular Undergraduate Programs',
                'summary' => 'Comprehensive policy governing modular course delivery, continuous assessment weighting, and academic standing rules across all colleges.',
                'cat' => $catAcad, 'conf' => $confPub, 'unit' => $unitVpaa, 'creator' => $approverId, 'custodian' => $approverId,
                'days_ago' => 45, 'lifecycle' => 'active', 'versions' => 3,
            ],
            [
                'ref' => 'WU-DOC-2026-0004',
                'title' => 'Annual Institutional Strategic Plan Review (2025–2030 Horizon)',
                'summary' => 'Mid-term evaluation of institutional performance indicators, enrollment metrics, research output targets, and internationalization milestones.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $recordsId,
                'days_ago' => 60, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0005',
                'title' => 'Code of Conduct and Academic Integrity Charter for Faculty and Students',
                'summary' => 'Statutory regulations regarding plagiarism prevention, examination conduct, disciplinary committee procedures, and conflict of interest.',
                'cat' => $catLeg, 'conf' => $confPub, 'unit' => $unitWu, 'creator' => $approverId, 'custodian' => $adminId,
                'days_ago' => 90, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0006',
                'title' => 'Senate Resolution on Post-Graduate Research Fellowship Grant Allocations',
                'summary' => 'Formal authorization of seed grants and doctoral stipends for STEM and health sciences doctoral candidates.',
                'cat' => $catRes, 'conf' => $confInt, 'unit' => $unitVpaa, 'creator' => $approverId, 'custodian' => $researchId,
                'days_ago' => 35, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0007',
                'title' => 'MoU on Collaborative Artificial Intelligence Research with INSA and AAII',
                'summary' => 'Bilateral agreement for faculty exchange, shared compute infrastructure, and joint cybersecurity laboratory establishment.',
                'cat' => $catRes, 'conf' => $confInt, 'unit' => $unitRcsd, 'creator' => $researchId, 'custodian' => $researchId,
                'days_ago' => 20, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0008',
                'title' => 'Emergency Disaster Readiness and Campus Safety Protocol',
                'summary' => 'Safety procedures for student residential halls, laboratory chemical storage, fire evacuation plans, and medical emergency escalation.',
                'cat' => $catAdmin, 'conf' => $confPub, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $recordsId,
                'days_ago' => 120, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0009',
                'title' => 'Superseded Examination Regulations 2021 (Archival Copy)',
                'summary' => 'Historical academic evaluation regulations officially replaced by the 2025 Revised Framework.',
                'cat' => $catLeg, 'conf' => $confInt, 'unit' => $unitVpaa, 'creator' => $approverId, 'custodian' => $recordsId,
                'days_ago' => 300, 'lifecycle' => 'retired', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0010',
                'title' => 'Revoked Vendor Agreement for Canteen Services Lot-4 (Tombstoned)',
                'summary' => 'Vendor procurement agreement canceled due to contractual non-compliance. Preserved for legal dispute ledger.',
                'cat' => $catFin, 'conf' => $confConf, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $financeId,
                'days_ago' => 180, 'lifecycle' => 'tombstoned', 'versions' => 1,
            ],

            // 11-20: Academic Affairs & Curriculum Accreditations
            [
                'ref' => 'WU-DOC-2026-0011',
                'title' => 'BSc in Software Engineering Curriculum Modernization Proposal',
                'summary' => 'Curriculum revision document incorporating Cloud Computing, DevSecOps, and Machine Learning modules for ABET accreditation alignment.',
                'cat' => $catAcad, 'conf' => $confInt, 'unit' => $unitCs, 'creator' => $headCsId, 'custodian' => $headCsId,
                'days_ago' => 12, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0012',
                'title' => 'MSc in Cybersecurity and Information Assurance Program Launch Document',
                'summary' => 'Needs assessment, course catalog, faculty qualification audit, and laboratory compute requirements for new graduate offering.',
                'cat' => $catAcad, 'conf' => $confInt, 'unit' => $unitCs, 'creator' => $headCsId, 'custodian' => $deanEngId,
                'days_ago' => 18, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0013',
                'title' => 'College of Technology Accreditation Self-Evaluation Report (HERQA Audit)',
                'summary' => 'Institutional quality assurance report submitted for national accreditation review of engineering degree streams.',
                'cat' => $catAcad, 'conf' => $confInt, 'unit' => $unitQao, 'creator' => $adminId, 'custodian' => $approverId,
                'days_ago' => 40, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0014',
                'title' => 'Faculty Course Load Guidelines and Credit Equivalency Standards',
                'summary' => 'Official directive specifying teaching load hours, thesis advisory weightings, and research reduction allowances.',
                'cat' => $catAcad, 'conf' => $confInt, 'unit' => $unitVpaa, 'creator' => $approverId, 'custodian' => $approverId,
                'days_ago' => 70, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0015',
                'title' => 'Postgraduate Academic Commission Fall Defense Schedule and Panelist Rosters',
                'summary' => 'Master of Science thesis examination schedules, external examiner assignments, and defense venue allocations.',
                'cat' => $catAcad, 'conf' => $confInt, 'unit' => $unitCote, 'creator' => $deanEngId, 'custodian' => $headCsId,
                'days_ago' => 10, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0016',
                'title' => 'Academic Calendar Revision for 2026/2027 Academic Year (Semester I & II)',
                'summary' => 'Updated dates for student registration, class commencement, midterm examination periods, and inter-semester breaks.',
                'cat' => $catAcad, 'conf' => $confPub, 'unit' => $unitVpaa, 'creator' => $approverId, 'custodian' => $recordsId,
                'days_ago' => 5, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0017',
                'title' => 'Electrical & Computer Engineering Laboratory Safety Guidelines',
                'summary' => 'High-voltage laboratory protocols, personal protective equipment standards, and emergency isolation procedures.',
                'cat' => $catAcad, 'conf' => $confPub, 'unit' => $unitEe, 'creator' => $deanEngId, 'custodian' => $deanEngId,
                'days_ago' => 85, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0018',
                'title' => 'Undergraduate Senior Capstone Project Guidelines and Assessment Rubrics',
                'summary' => 'Industry-partnered capstone milestones, intellectual property disclosure forms, and peer review grading metrics.',
                'cat' => $catAcad, 'conf' => $confPub, 'unit' => $unitCs, 'creator' => $staffId, 'custodian' => $headCsId,
                'days_ago' => 30, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0019',
                'title' => 'Department of Nursing Clinical Practicum Protocol at Dessie Referral Hospital',
                'summary' => 'Clinical training agreement, patient confidentiality requirements, and supervisor logbook templates.',
                'cat' => $catAcad, 'conf' => $confInt, 'unit' => $unitWu, 'creator' => $approverId, 'custodian' => $recordsId,
                'days_ago' => 110, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0020',
                'title' => 'Historical Curriculum Document: BSc Computer Science (2018 Harmonized)',
                'summary' => 'Archival curriculum documentation retained for degree verification and transcript equivalency requests.',
                'cat' => $catAcad, 'conf' => $confInt, 'unit' => $unitCs, 'creator' => $headCsId, 'custodian' => $recordsId,
                'days_ago' => 365, 'lifecycle' => 'retired', 'versions' => 1,
            ],

            // 21-30: Human Resources & Personnel Files
            [
                'ref' => 'WU-DOC-2026-0021',
                'title' => 'Academic Staff Promotion Committee Dossier: Dr. Dawit Tadesse to Associate Professor',
                'summary' => 'Evaluation of publications, teaching effectiveness, institutional service, and external reviewer testimonials.',
                'cat' => $catHr, 'conf' => $confConf, 'unit' => $unitHrmd, 'creator' => $hrId, 'custodian' => $hrId,
                'days_ago' => 22, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0022',
                'title' => 'Institutional Staffing Establishment and Academic Recruitment Plan 2026/27',
                'summary' => 'Vacancy projections, departmental FTE allocations, and expatriate faculty hiring authorizations.',
                'cat' => $catHr, 'conf' => $confConf, 'unit' => $unitHrmd, 'creator' => $hrId, 'custodian' => $hrId,
                'days_ago' => 16, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0023',
                'title' => 'Faculty Study Leave Approval and Scholarship Agreement: Ephrem Negash',
                'summary' => 'Doctoral study fellowship sponsorship, university service bond, and salary continuation terms.',
                'cat' => $catHr, 'conf' => $confConf, 'unit' => $unitHrmd, 'creator' => $hrId, 'custodian' => $hrId,
                'days_ago' => 28, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0024',
                'title' => 'University Technical Staff Job Reclassification and Salary Harmonization Scale',
                'summary' => 'National Civil Service scale alignment for laboratory technicians, network administrators, and librarians.',
                'cat' => $catHr, 'conf' => $confInt, 'unit' => $unitHrmd, 'creator' => $hrId, 'custodian' => $hrId,
                'days_ago' => 95, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0025',
                'title' => 'Health Insurance Benefit Contract for Permanent University Employees',
                'summary' => 'Institutional health coverage policy, participating hospital networks, and dependent coverage allowances.',
                'cat' => $catHr, 'conf' => $confInt, 'unit' => $unitHrmd, 'creator' => $hrId, 'custodian' => $financeId,
                'days_ago' => 140, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0026',
                'title' => 'Staff Performance Appraisal Guidelines and 360-Degree Evaluation Rubric',
                'summary' => 'Quarterly appraisal forms, self-assessment criteria, supervisor review weights, and performance bonuses.',
                'cat' => $catHr, 'conf' => $confInt, 'unit' => $unitHrmd, 'creator' => $hrId, 'custodian' => $hrId,
                'days_ago' => 55, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0027',
                'title' => 'Disciplinary Hearing Decision Memo: Academic Malpractice Resolution',
                'summary' => 'Confidential proceedings of staff disciplinary panel regarding unauthorized grade alterations.',
                'cat' => $catHr, 'conf' => $confRest, 'unit' => $unitHrmd, 'creator' => $adminId, 'custodian' => $hrId,
                'days_ago' => 65, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0028',
                'title' => 'University Sabbatical Leave Policy and Research Milestone Deliverables',
                'summary' => 'Rules governing sabbatical leave eligibility after seven years of continuous academic service.',
                'cat' => $catHr, 'conf' => $confPub, 'unit' => $unitHrmd, 'creator' => $hrId, 'custodian' => $approverId,
                'days_ago' => 125, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0029',
                'title' => 'Retired Employee Service Pension Clearances Batch 2024 (Archived)',
                'summary' => 'Archival records of pension entitlements, department property handovers, and final severance settlements.',
                'cat' => $catHr, 'conf' => $confConf, 'unit' => $unitHrmd, 'creator' => $hrId, 'custodian' => $recordsId,
                'days_ago' => 310, 'lifecycle' => 'retired', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0030',
                'title' => 'Rescinded Employment Offer Letter: Adjunct Faculty Fall 2025 (Tombstoned)',
                'summary' => 'Revoked offer following credential verification discrepancy discovered during background screening.',
                'cat' => $catHr, 'conf' => $confRest, 'unit' => $unitHrmd, 'creator' => $hrId, 'custodian' => $hrId,
                'days_ago' => 200, 'lifecycle' => 'tombstoned', 'versions' => 1,
            ],

            // 31-40: Finance, Procurement & Audits
            [
                'ref' => 'WU-DOC-2026-0031',
                'title' => 'Wollo University Quarter 3 Consolidated Financial Performance and Budget Utilization Report',
                'summary' => 'Detailed expenditure report across operating budgets, capital projects, research grants, and internal revenue.',
                'cat' => $catFin, 'conf' => $confInt, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $financeId,
                'days_ago' => 8, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0032',
                'title' => 'International Competitive Bid Evaluation: High-Performance Computing Cluster Lot-1',
                'summary' => 'Technical and financial tender evaluation report for AI and genomics research compute cluster acquisition.',
                'cat' => $catFin, 'conf' => $confConf, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $financeId,
                'days_ago' => 14, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0033',
                'title' => 'Federal Auditor General Annual Financial Audit Compliance and Remediation Plan',
                'summary' => 'Institutional response and corrective action plan addressing national audit findings on fixed asset tagging.',
                'cat' => $catFin, 'conf' => $confConf, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $financeId,
                'days_ago' => 50, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0034',
                'title' => 'Petty Cash Operating Ceiling and Departmental Emergency Advance Directives',
                'summary' => 'Revised financial controls governing unit petty cash replenishment, voucher verification, and liquidation limits.',
                'cat' => $catFin, 'conf' => $confInt, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $financeId,
                'days_ago' => 75, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0035',
                'title' => 'Per Diem, Travel Allowance and Field Research Expense Reimbursement Scale 2026',
                'summary' => 'Updated domestic and international travel allowance schedule based on revised federal civil service standards.',
                'cat' => $catFin, 'conf' => $confPub, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $recordsId,
                'days_ago' => 100, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0036',
                'title' => 'Procurement Contract: Campus-Wide Enterprise Fiber Optic Backbone Replacement',
                'summary' => 'Signed commercial contract with Ethio Telecom for redundant dark fiber rings connecting Dessie and Kombolcha campuses.',
                'cat' => $catFin, 'conf' => $confConf, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $adminId,
                'days_ago' => 42, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0037',
                'title' => 'Laboratory Chemicals and Reagents Annual Bulk Tender Award Notification',
                'summary' => 'Procurement award notifications for chemistry, biology, and health sciences teaching consumables.',
                'cat' => $catFin, 'conf' => $confInt, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $financeId,
                'days_ago' => 88, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0038',
                'title' => 'Internal Audit Report on Revenue Collection and Student Service Cafeteria Operations',
                'summary' => 'Special investigation into meal ticket reconciliations and student union store leasing contracts.',
                'cat' => $catFin, 'conf' => $confConf, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $adminId,
                'days_ago' => 62, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0039',
                'title' => 'Fiscal Year 2023/24 Budget Settlement Ledger (Archived Financial Records)',
                'summary' => 'Closed fiscal year accounts, bank reconciliation certificates, and final trial balance archives.',
                'cat' => $catFin, 'conf' => $confConf, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $recordsId,
                'days_ago' => 320, 'lifecycle' => 'retired', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0040',
                'title' => 'Canceled Tender Notice: Stationary Goods Supply RFQ-2025-089 (Tombstoned)',
                'summary' => 'Procurement process canceled due to supplier bid collusion identified by internal procurement review board.',
                'cat' => $catFin, 'conf' => $confInt, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $financeId,
                'days_ago' => 190, 'lifecycle' => 'tombstoned', 'versions' => 1,
            ],

            // 41-50: Research, Publications & Grants
            [
                'ref' => 'WU-DOC-2026-0041',
                'title' => 'Final Research Report: Edge-AI Powered Disease Detection in Highland Teff Cultivars',
                'summary' => 'Comprehensive technical report on convolutional neural network deployment on mobile edge devices for crop rust identification.',
                'cat' => $catRes, 'conf' => $confPub, 'unit' => $unitRcsd, 'creator' => $headCsId, 'custodian' => $researchId,
                'days_ago' => 15, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0042',
                'title' => 'Annual Wollo University Journal of Science and Technology (WUJST) Volume 8 Issue 2',
                'summary' => 'Peer-reviewed research compendium featuring 12 original engineering, health, and natural science contributions.',
                'cat' => $catRes, 'conf' => $confPub, 'unit' => $unitRcsd, 'creator' => $researchId, 'custodian' => $researchId,
                'days_ago' => 28, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0043',
                'title' => 'Research Grant Proposal: Hybrid Solar-Wind Microgrid Architecture for Off-Grid Clinics',
                'summary' => 'Collaborative grant proposal submitted to the Ministry of Innovation and Technology (MInT) requesting 4.2M ETB in research funding.',
                'cat' => $catRes, 'conf' => $confInt, 'unit' => $unitEe, 'creator' => $deanEngId, 'custodian' => $researchId,
                'days_ago' => 33, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0044',
                'title' => 'Institutional Review Board (IRB) Ethical Clearance: Community Health Survey South Wollo',
                'summary' => 'Ethical approval certificate, informed consent protocols, and data anonymization safeguards for epidemiological study.',
                'cat' => $catRes, 'conf' => $confInt, 'unit' => $unitRcsd, 'creator' => $researchId, 'custodian' => $researchId,
                'days_ago' => 48, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0045',
                'title' => 'Intellectual Property and Patent Disclosure: Low-Cost Infant Phototherapy Incubator',
                'summary' => 'Patent application filing with the Ethiopian Intellectual Property Authority (EIPA) originating from biomedical engineering faculty.',
                'cat' => $catRes, 'conf' => $confConf, 'unit' => $unitRcsd, 'creator' => $researchId, 'custodian' => $researchId,
                'days_ago' => 72, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0046',
                'title' => 'Community Technology Transfer Project Report: Solar Water Pumping in Werebabo Woreda',
                'summary' => 'Impact assessment and technical handover documentation for community potable water infrastructure installed by Wollo faculty.',
                'cat' => $catRes, 'conf' => $confPub, 'unit' => $unitRcsd, 'creator' => $deanEngId, 'custodian' => $researchId,
                'days_ago' => 92, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0047',
                'title' => 'Conference Proceedings: 7th National Multidisciplinary Research Symposium',
                'summary' => 'Full proceedings, keynote transcripts, and abstract book for symposium hosted at Wollo University Dessie Campus.',
                'cat' => $catRes, 'conf' => $confPub, 'unit' => $unitRcsd, 'creator' => $researchId, 'custodian' => $recordsId,
                'days_ago' => 105, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0048',
                'title' => 'National Research Grant Final Expenditure Audit: Agroforestry Carbon Sequestration Study',
                'summary' => 'Financial closeout report confirming full grant disbursement compliance for 3-year ecological study.',
                'cat' => $catRes, 'conf' => $confInt, 'unit' => $unitRcsd, 'creator' => $financeId, 'custodian' => $researchId,
                'days_ago' => 130, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0049',
                'title' => 'Completed Research Archive: Traditional Medicinal Plant Efficacy Trial 2022',
                'summary' => 'Archival records of pharmaceutical assay data and ethnobotanical survey records preserved in university repository.',
                'cat' => $catRes, 'conf' => $confPub, 'unit' => $unitRcsd, 'creator' => $researchId, 'custodian' => $recordsId,
                'days_ago' => 340, 'lifecycle' => 'retired', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0050',
                'title' => 'Withdrawn Research Paper: Preliminary Survey of Regional Ground Water Salinity (Tombstoned)',
                'summary' => 'Manuscript officially retracted following calibration error discovered in laboratory spectrometry equipment.',
                'cat' => $catRes, 'conf' => $confInt, 'unit' => $unitRcsd, 'creator' => $researchId, 'custodian' => $researchId,
                'days_ago' => 210, 'lifecycle' => 'tombstoned', 'versions' => 1,
            ],

            // 51-60: Official Correspondence & Inter-Office Memos
            [
                'ref' => 'WU-DOC-2026-0051',
                'title' => 'Incoming Letter from Ministry of Education: National Exit Examination Guidelines 2026',
                'summary' => 'Federal directives regarding graduating student online testing venues, biometric verification, and exam center rules.',
                'cat' => $catAdmin, 'conf' => $confPub, 'unit' => $unitCro, 'creator' => $recordsId, 'custodian' => $recordsId,
                'days_ago' => 4, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0052',
                'title' => 'Outgoing Official Response to Ministry of Education Regarding Exit Exam Readiness',
                'summary' => 'Presidential reply confirming computer laboratory seating capacity, backup generator tests, and proctoring rosters.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitWu, 'creator' => $approverId, 'custodian' => $recordsId,
                'days_ago' => 2, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0053',
                'title' => 'Incoming Notification from HERQA: Institutional Re-Accreditation On-Site Visit Date',
                'summary' => 'Official audit notice scheduling external quality assessor peer team visit to Dessie and Kombolcha campuses.',
                'cat' => $catAcad, 'conf' => $confInt, 'unit' => $unitQao, 'creator' => $recordsId, 'custodian' => $approverId,
                'days_ago' => 7, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0054',
                'title' => 'Internal Memorandum: Department of Computer Science Laboratory Utilization Schedule',
                'summary' => 'Memorandum from CS Head to College Dean requesting extended weekend lab hours for postgraduate research.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitCs, 'creator' => $headCsId, 'custodian' => $deanEngId,
                'days_ago' => 6, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0055',
                'title' => 'Incoming Partnership Proposal from Addis Ababa University: Joint Doctoral Consortium',
                'summary' => 'Consortium proposal for co-advising PhD students in artificial intelligence and biomedical informatics.',
                'cat' => $catAcad, 'conf' => $confPub, 'unit' => $unitVpaa, 'creator' => $recordsId, 'custodian' => $approverId,
                'days_ago' => 14, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0056',
                'title' => 'Outgoing Dispatch to Commercial Bank of Ethiopia: Student Fee Settlement Authorization',
                'summary' => 'Authorized electronic payment clearing instruction for semester student meal subsidies and campus allowances.',
                'cat' => $catFin, 'conf' => $confConf, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $recordsId,
                'days_ago' => 11, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0057',
                'title' => 'Internal Memorandum: ICT Directorate Maintenance Window and Network Interruption Notice',
                'summary' => 'Planned power maintenance schedule affecting core server room UPS infrastructure and wireless gateways.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $recordsId,
                'days_ago' => 3, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0058',
                'title' => 'Incoming Circular from Amhara Regional Health Bureau: Seasonal Malaria Prevention Campaign',
                'summary' => 'Collaborative mosquito bed net distribution and spraying schedule coordinated with University Health Sciences staff.',
                'cat' => $catAdmin, 'conf' => $confPub, 'unit' => $unitWu, 'creator' => $recordsId, 'custodian' => $recordsId,
                'days_ago' => 21, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0059',
                'title' => 'Archived Correspondence: 2023 National University Sports Championship Hosting Agreement',
                'summary' => 'Historical inter-university sports protocol and venue lease agreements retained for facility records.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitCro, 'creator' => $recordsId, 'custodian' => $recordsId,
                'days_ago' => 350, 'lifecycle' => 'retired', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0060',
                'title' => 'Expired Memorandum of Understanding: Regional Small Business Advisory Hub 2022 (Tombstoned)',
                'summary' => 'Lapsed agreement between College of Business and local trade bureau, formally terminated without renewal.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitCbbe, 'creator' => $recordsId, 'custodian' => $recordsId,
                'days_ago' => 220, 'lifecycle' => 'tombstoned', 'versions' => 1,
            ],

            // 61-75: Operations, Accreditation & Archival Records
            [
                'ref' => 'WU-DOC-2026-0061',
                'title' => 'Quality Assurance Peer Review Summary: Department of Electrical Engineering',
                'summary' => 'Comprehensive standard-by-standard evidence verification and rubric grading conducted by internal QA auditors.',
                'cat' => $catAcad, 'conf' => $confInt, 'unit' => $unitQao, 'creator' => $adminId, 'custodian' => $deanEngId,
                'days_ago' => 19, 'lifecycle' => 'active', 'versions' => 2,
            ],
            [
                'ref' => 'WU-DOC-2026-0062',
                'title' => 'Institutional Disaster Recovery Drill Evaluation Report and Backup Integrity Audit',
                'summary' => 'Semi-annual testing of offsite backup archives, MySQL 8.4 database restoration benchmarks, and SHA-256 verification.',
                'cat' => $catAdmin, 'conf' => $confConf, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $adminId,
                'days_ago' => 9, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0063',
                'title' => 'University Library Digital Repository Expansion and Open Access Mandate',
                'summary' => 'Directive requiring digital deposit of all faculty peer-reviewed manuscripts and graduate theses in DSpace repository.',
                'cat' => $catAcad, 'conf' => $confPub, 'unit' => $unitVpaa, 'creator' => $approverId, 'custodian' => $recordsId,
                'days_ago' => 38, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0064',
                'title' => 'Public Private Partnership Proposal: Commercial Rooftop Solar Array Installation',
                'summary' => 'Technical feasibility study, tariff purchase agreement, and carbon offset certificate calculations.',
                'cat' => $catFin, 'conf' => $confInt, 'unit' => $unitFpd, 'creator' => $financeId, 'custodian' => $financeId,
                'days_ago' => 44, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0065',
                'title' => 'Office of the Registrar Statistical Digest: Enrollment, Retention and Graduation 2025',
                'summary' => 'Demographic breakdown of student body across undergraduate, postgraduate, and extension degree programs.',
                'cat' => $catAcad, 'conf' => $confPub, 'unit' => $unitVpaa, 'creator' => $approverId, 'custodian' => $recordsId,
                'days_ago' => 68, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0066',
                'title' => 'Standard Operating Procedure: Official University Seal and Digital Signature Usage',
                'summary' => 'Cryptographic security protocols, designated institutional signing officers, and revocation policies.',
                'cat' => $catLeg, 'conf' => $confRest, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $adminId,
                'days_ago' => 52, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0067',
                'title' => 'Disposal Authorization Certificate: Obsolete CRT Monitors and Legacy Telephony Hardware',
                'summary' => 'Formal authorization from university property disposal committee for environmentally safe e-waste recycling.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitCro, 'creator' => $adminId, 'custodian' => $recordsId,
                'days_ago' => 80, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0068',
                'title' => 'Campus Green Initiative and Waste Segregation Master Protocol',
                'summary' => 'University-wide guidelines for organic waste composting, plastic reduction in cafeterias, and paper recycling.',
                'cat' => $catAdmin, 'conf' => $confPub, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $recordsId,
                'days_ago' => 98, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0069',
                'title' => 'Security Audit Report: Physical Access Control Systems at Kombolcha Research Lab',
                'summary' => 'Vulnerability assessment of biometric turnstiles, CCTV retention servers, and perimeter sensor logging.',
                'cat' => $catAdmin, 'conf' => $confConf, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $adminId,
                'days_ago' => 78, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0070',
                'title' => 'Academic Dishonesty and Plagiarism Investigation: Senior Essay Batch 2025/1',
                'summary' => 'Turnitin similarity analysis reports, disciplinary committee findings, and degree revocation recommendations.',
                'cat' => $catAcad, 'conf' => $confRest, 'unit' => $unitVpaa, 'creator' => $approverId, 'custodian' => $approverId,
                'days_ago' => 84, 'lifecycle' => 'active', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0071',
                'title' => 'Wollo University Archival Collection Catalogue: Founding Charter and Historical Deeds (1998–2005)',
                'summary' => 'Complete accession register of university founding land grants, parliamentary decrees, and presidential inaugurations.',
                'cat' => $catLeg, 'conf' => $confPub, 'unit' => $unitCro, 'creator' => $recordsId, 'custodian' => $recordsId,
                'days_ago' => 150, 'lifecycle' => 'retired', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0072',
                'title' => 'Historical Records: Dessie Campus Medical Center Construction Blueprints (Archived)',
                'summary' => 'Architectural elevations, structural engineering drawings, and municipal building permits.',
                'cat' => $catAdmin, 'conf' => $confInt, 'unit' => $unitCro, 'creator' => $recordsId, 'custodian' => $recordsId,
                'days_ago' => 280, 'lifecycle' => 'retired', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0073',
                'title' => 'Terminated Grant Sub-Agreement: European Commission Capacity Building Project (Tombstoned)',
                'summary' => 'Sub-award terminated due to restructuring of international consortium following donor mandate modifications.',
                'cat' => $catRes, 'conf' => $confConf, 'unit' => $unitRcsd, 'creator' => $researchId, 'custodian' => $researchId,
                'days_ago' => 230, 'lifecycle' => 'tombstoned', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0074',
                'title' => 'Historical Faculty Roster and Rank Register: Academic Year 2015/16 (Archived)',
                'summary' => 'Legacy faculty appointment records maintained for historical tenure service and pension validation.',
                'cat' => $catHr, 'conf' => $confInt, 'unit' => $unitCro, 'creator' => $hrId, 'custodian' => $recordsId,
                'days_ago' => 360, 'lifecycle' => 'retired', 'versions' => 1,
            ],
            [
                'ref' => 'WU-DOC-2026-0075',
                'title' => 'Institutional Accreditation Certificate: Ministry of Education Gold Standard 2025–2030',
                'summary' => 'Official national higher education accreditation seal certifying Wollo University compliance with all quality standards.',
                'cat' => $catAcad, 'conf' => $confPub, 'unit' => $unitWu, 'creator' => $adminId, 'custodian' => $recordsId,
                'days_ago' => 1, 'lifecycle' => 'active', 'versions' => 1,
            ],
        ];

        foreach ($documentsCatalog as $docData) {
            $regDate = Carbon::now()->subDays($docData['days_ago']);

            // 1. Create or Update Document
            $doc = Document::updateOrCreate(
                ['reference_number' => $docData['ref']],
                [
                    'title' => $docData['title'],
                    'summary' => $docData['summary'],
                    'category_id' => $docData['cat'],
                    'confidentiality_id' => $docData['conf'],
                    'owner_unit_id' => $docData['unit'],
                    'custodian_id' => $docData['custodian'],
                    'created_by' => $docData['creator'],
                    'document_date' => $regDate->toDateString(),
                    'registered_at' => $regDate,
                    'lifecycle' => $docData['lifecycle'],
                    'lock_version' => 0,
                    'created_at' => $regDate,
                    'updated_at' => $regDate,
                ]
            );

            // 2. Create Document Version 1
            $v1 = DocumentVersion::firstOrCreate(
                ['document_id' => $doc->id, 'version_number' => 1],
                [
                    'created_by' => $docData['creator'],
                    'change_note' => 'Initial document registration and official artifact ingest',
                    'created_at' => $regDate,
                ]
            );

            // 3. Generate Genuine Physical File Payload for Version 1
            $vf1 = VersionFile::where('document_version_id', $v1->id)->first();
            if (! $vf1) {
                $pdfFile = DemoFileGenerator::createPdf(
                    originalFilename: "{$docData['ref']}_v1.pdf",
                    title: $docData['title'],
                    refNumber: $docData['ref'],
                    body: $docData['summary'],
                    uploadedBy: $docData['creator'],
                    year: '2026'
                );

                VersionFile::firstOrCreate(
                    [
                        'document_version_id' => $v1->id,
                        'file_object_id' => $pdfFile->id,
                    ],
                    [
                        'purpose' => 'primary',
                        'display_order' => 1,
                    ]
                );
            }

            // 5. If document has multiple versions, generate Version 2 (and 3)
            if (($docData['versions'] ?? 1) >= 2) {
                $v2Date = (clone $regDate)->addDays(3);
                $v2 = DocumentVersion::firstOrCreate(
                    ['document_id' => $doc->id, 'version_number' => 2],
                    [
                        'created_by' => $docData['creator'],
                        'change_note' => 'Revised draft incorporating reviewer feedback and updated institutional appendices',
                        'created_at' => $v2Date,
                    ]
                );

                $vf2 = VersionFile::where('document_version_id', $v2->id)->first();
                if (! $vf2) {
                    $pdfFileV2 = DemoFileGenerator::createPdf(
                        originalFilename: "{$docData['ref']}_v2_revised.pdf",
                        title: "{$docData['title']} (Revised Version 2)",
                        refNumber: "{$docData['ref']}/v2",
                        body: "{$docData['summary']} Updated revision incorporating faculty and administrative feedback.",
                        uploadedBy: $docData['creator'],
                        year: '2026'
                    );

                    VersionFile::firstOrCreate(
                        [
                            'document_version_id' => $v2->id,
                            'file_object_id' => $pdfFileV2->id,
                        ],
                        [
                            'purpose' => 'primary',
                            'display_order' => 1,
                        ]
                    );
                }

                if (($docData['versions'] ?? 1) >= 3) {
                    $v3Date = (clone $regDate)->addDays(7);
                    $v3 = DocumentVersion::firstOrCreate(
                        ['document_id' => $doc->id, 'version_number' => 3],
                        [
                            'created_by' => $docData['creator'],
                            'change_note' => 'Final ratified and approved executive version',
                            'created_at' => $v3Date,
                        ]
                    );

                    $vf3 = VersionFile::where('document_version_id', $v3->id)->first();
                    if (! $vf3) {
                        $pdfFileV3 = DemoFileGenerator::createPdf(
                            originalFilename: "{$docData['ref']}_v3_final.pdf",
                            title: "{$docData['title']} (Final Approved)",
                            refNumber: "{$docData['ref']}/v3",
                            body: "{$docData['summary']} Final executive approved release ratified by the academic commission.",
                            uploadedBy: $docData['creator'],
                            year: '2026'
                        );

                        VersionFile::firstOrCreate(
                            [
                                'document_version_id' => $v3->id,
                                'file_object_id' => $pdfFileV3->id,
                            ],
                            [
                                'purpose' => 'primary',
                                'display_order' => 1,
                            ]
                        );
                    }
                }
            }

            // 6. Create realistic Document Movements for active documents
            if ($docData['lifecycle'] === 'active' && ($docData['days_ago'] > 5)) {
                $fromUnit = ($docData['unit'] === $unitCro) ? $unitVpaa : $unitCro;
                $toUnit = $docData['unit'];

                DocumentMovement::firstOrCreate(
                    ['document_id' => $doc->id, 'idempotency_key' => "MOV-{$docData['ref']}-01"],
                    [
                        'from_unit_id' => $fromUnit,
                        'to_unit_id' => $toUnit,
                        'sent_by' => $recordsId,
                        'sent_at' => $regDate,
                        'instruction' => 'Forwarded from central registry for departmental action and institutional retention.',
                        'due_at' => (clone $regDate)->addDays(14),
                    ]
                );
            }

            // 7. Create realistic Document Comments
            if ($docData['days_ago'] > 10) {
                DocumentComment::firstOrCreate(
                    ['document_id' => $doc->id, 'body' => "Document verified against institutional filing criteria. Registered into central catalog."],
                    [
                        'author_id' => $recordsId,
                        'created_at' => (clone $regDate)->addHours(2),
                    ]
                );

                if (($docData['versions'] ?? 1) >= 2) {
                    DocumentComment::firstOrCreate(
                        ['document_id' => $doc->id, 'body' => "Peer review revisions approved. Recommended for executive sign-off."],
                        [
                            'author_id' => $headCsId,
                            'created_at' => (clone $regDate)->addDays(3),
                        ]
                    );
                }
            }

            // 8. Create Document Assignments
            if ($docData['days_ago'] < 20 && $docData['lifecycle'] === 'active') {
                DocumentAssignment::firstOrCreate(
                    ['document_id' => $doc->id],
                    [
                        'assigned_user_id' => $headCsId,
                        'assigned_unit_id' => $docData['unit'],
                        'assigned_by' => $adminId,
                        'assigned_at' => $regDate,
                        'due_at' => (clone $regDate)->addDays(14),
                        'instruction' => 'Please review and coordinate departmental feedback for executive committee review.',
                    ]
                );
            }
        }
    }
}
