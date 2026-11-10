<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\AuthorizationScope;
use App\Models\OrganizationUnit;
use App\Models\UnitType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoOrganizationSeeder extends Seeder
{
    /**
     * Seed realistic institutional demonstration organization units and scopes.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Resolve Unit Types
        $universityType = UnitType::firstOrCreate(['code' => 'university'], ['name' => 'University', 'active' => 1]);
        $directorateType = UnitType::firstOrCreate(['code' => 'directorate'], ['name' => 'Directorate', 'active' => 1]);
        $collegeType = UnitType::firstOrCreate(['code' => 'college'], ['name' => 'College', 'active' => 1]);
        $departmentType = UnitType::firstOrCreate(['code' => 'department'], ['name' => 'Department', 'active' => 1]);
        $officeType = UnitType::firstOrCreate(['code' => 'office'], ['name' => 'Office', 'active' => 1]);

        // 2. Root Institution
        $root = OrganizationUnit::firstOrCreate(
            ['code' => 'WU'],
            [
                'name' => 'Wollo University',
                'parent_id' => null,
                'unit_type_id' => $universityType->id,
                'active' => 1,
            ]
        );

        // 3. Vice Presidential Offices & Executive Units
        $vpaa = OrganizationUnit::firstOrCreate(
            ['code' => 'VPAA'],
            [
                'name' => 'Office of the Vice President for Academic Affairs',
                'parent_id' => $root->id,
                'unit_type_id' => $officeType->id,
                'active' => 1,
            ]
        );

        $vprcs = OrganizationUnit::firstOrCreate(
            ['code' => 'VPRCS'],
            [
                'name' => 'Office of the Vice President for Research and Community Services',
                'parent_id' => $root->id,
                'unit_type_id' => $officeType->id,
                'active' => 1,
            ]
        );

        $vpaf = OrganizationUnit::firstOrCreate(
            ['code' => 'VPAF'],
            [
                'name' => 'Office of the Vice President for Administration and Finance',
                'parent_id' => $root->id,
                'unit_type_id' => $officeType->id,
                'active' => 1,
            ]
        );

        $cro = OrganizationUnit::firstOrCreate(
            ['code' => 'CRO'],
            [
                'name' => 'Central Records and Archives Office',
                'parent_id' => $root->id,
                'unit_type_id' => $directorateType->id,
                'active' => 1,
            ]
        );

        $iao = OrganizationUnit::firstOrCreate(
            ['code' => 'IAO'],
            [
                'name' => 'Internal Audit Directorate',
                'parent_id' => $root->id,
                'unit_type_id' => $directorateType->id,
                'active' => 1,
            ]
        );

        // 4. Central Service Directorates under VPAF / VPAA / VPRCS
        $ictd = OrganizationUnit::firstOrCreate(
            ['code' => 'ICTD'],
            [
                'name' => 'Information and Communications Technology Directorate',
                'parent_id' => $vpaf->id,
                'unit_type_id' => $directorateType->id,
                'active' => 1,
            ]
        );

        $hrmd = OrganizationUnit::firstOrCreate(
            ['code' => 'HRMD'],
            [
                'name' => 'Human Resource Management Directorate',
                'parent_id' => $vpaf->id,
                'unit_type_id' => $directorateType->id,
                'active' => 1,
            ]
        );

        $fpd = OrganizationUnit::firstOrCreate(
            ['code' => 'FPD'],
            [
                'name' => 'Finance and Procurement Directorate',
                'parent_id' => $vpaf->id,
                'unit_type_id' => $directorateType->id,
                'active' => 1,
            ]
        );

        $qao = OrganizationUnit::firstOrCreate(
            ['code' => 'QAO'],
            [
                'name' => 'Quality Assurance and Accreditation Office',
                'parent_id' => $vpaa->id,
                'unit_type_id' => $officeType->id,
                'active' => 1,
            ]
        );

        $reg = OrganizationUnit::firstOrCreate(
            ['code' => 'REG'],
            [
                'name' => 'Office of the University Registrar',
                'parent_id' => $vpaa->id,
                'unit_type_id' => $directorateType->id,
                'active' => 1,
            ]
        );

        $rcsd = OrganizationUnit::firstOrCreate(
            ['code' => 'RCSD'],
            [
                'name' => 'Research and Community Services Directorate',
                'parent_id' => $vprcs->id,
                'unit_type_id' => $directorateType->id,
                'active' => 1,
            ]
        );

        $arch = OrganizationUnit::firstOrCreate(
            ['code' => 'ARCH'],
            [
                'name' => 'University Archival and Records Repository',
                'parent_id' => $cro->id,
                'unit_type_id' => $officeType->id,
                'active' => 1,
            ]
        );

        // 5. Academic Colleges & Departments
        $cote = OrganizationUnit::firstOrCreate(
            ['code' => 'COTE'],
            [
                'name' => 'College of Technology and Engineering (Kombolcha Campus)',
                'parent_id' => $vpaa->id,
                'unit_type_id' => $collegeType->id,
                'active' => 1,
            ]
        );

        $cs = OrganizationUnit::firstOrCreate(
            ['code' => 'CS'],
            [
                'name' => 'Department of Computer Science',
                'parent_id' => $cote->id,
                'unit_type_id' => $departmentType->id,
                'active' => 1,
            ]
        );

        $ee = OrganizationUnit::firstOrCreate(
            ['code' => 'EE'],
            [
                'name' => 'Department of Electrical Engineering',
                'parent_id' => $cote->id,
                'unit_type_id' => $departmentType->id,
                'active' => 1,
            ]
        );

        $cbbe = OrganizationUnit::firstOrCreate(
            ['code' => 'CBBE'],
            [
                'name' => 'College of Business and Economics',
                'parent_id' => $vpaa->id,
                'unit_type_id' => $collegeType->id,
                'active' => 1,
            ]
        );

        $mgt = OrganizationUnit::firstOrCreate(
            ['code' => 'MGT'],
            [
                'name' => 'Department of Management',
                'parent_id' => $cbbe->id,
                'unit_type_id' => $departmentType->id,
                'active' => 1,
            ]
        );

        $chs = OrganizationUnit::firstOrCreate(
            ['code' => 'CHS'],
            [
                'name' => 'College of Health Sciences',
                'parent_id' => $vpaa->id,
                'unit_type_id' => $collegeType->id,
                'active' => 1,
            ]
        );

        $nur = OrganizationUnit::firstOrCreate(
            ['code' => 'NUR'],
            [
                'name' => 'Department of Nursing',
                'parent_id' => $chs->id,
                'unit_type_id' => $departmentType->id,
                'active' => 1,
            ]
        );

        // 6. Establish Authorization Scopes
        // Global scope
        AuthorizationScope::firstOrCreate(
            ['kind' => 'global', 'unit_id' => null]
        );

        // Subtree scopes for colleges
        AuthorizationScope::firstOrCreate(['kind' => 'subtree', 'unit_id' => $cote->id]);
        AuthorizationScope::firstOrCreate(['kind' => 'subtree', 'unit_id' => $cbbe->id]);
        AuthorizationScope::firstOrCreate(['kind' => 'subtree', 'unit_id' => $chs->id]);

        // Unit-level scopes for all units
        $allUnits = OrganizationUnit::all();
        foreach ($allUnits as $unit) {
            AuthorizationScope::firstOrCreate([
                'kind' => 'unit',
                'unit_id' => $unit->id,
            ]);
        }
    }
}
