<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $presidentOffice = Department::firstOrCreate(
            ['code' => 'OP'],
            [
                'name' => 'Office of the President',
                'description' => 'Executive Office of the University President',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $academicVP = Department::firstOrCreate(
            ['code' => 'AAD'],
            [
                'name' => 'Academic Affairs Directorate',
                'description' => 'Academic programs, curriculum, quality assurance',
                'parent_id' => $presidentOffice->id,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $researchVP = Department::firstOrCreate(
            ['code' => 'RCS'],
            [
                'name' => 'Research and Community Service VP',
                'description' => 'Research directorates, publication, and technology transfer',
                'parent_id' => $presidentOffice->id,
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        Department::firstOrCreate(
            ['code' => 'RAD'],
            [
                'name' => 'Registrar and Alumni Directorate',
                'description' => 'Student records, admissions, transcripts, graduation',
                'parent_id' => $academicVP->id,
                'is_active' => true,
                'sort_order' => 4,
            ]
        );

        Department::firstOrCreate(
            ['code' => 'HRMD'],
            [
                'name' => 'Human Resource Management Directorate',
                'description' => 'Academic and administrative staff recruitment, benefits, records',
                'parent_id' => $presidentOffice->id,
                'is_active' => true,
                'sort_order' => 5,
            ]
        );

        Department::firstOrCreate(
            ['code' => 'FPD'],
            [
                'name' => 'Finance and Procurement Directorate',
                'description' => 'Budgeting, payroll, purchasing, asset management',
                'parent_id' => $presidentOffice->id,
                'is_active' => true,
                'sort_order' => 6,
            ]
        );

        Department::firstOrCreate(
            ['code' => 'ICTC'],
            [
                'name' => 'Information Communication Technology Center',
                'description' => 'Network infrastructure, software systems, user support',
                'parent_id' => $presidentOffice->id,
                'is_active' => true,
                'sort_order' => 7,
            ]
        );

        Department::firstOrCreate(
            ['code' => 'CMHS'],
            [
                'name' => 'College of Medicine and Health Sciences',
                'description' => 'Health sciences campus and referral hospital training',
                'parent_id' => $academicVP->id,
                'is_active' => true,
                'sort_order' => 8,
            ]
        );

        Department::firstOrCreate(
            ['code' => 'COA'],
            [
                'name' => 'College of Agriculture',
                'description' => 'Agricultural sciences, dryland farming, animal science',
                'parent_id' => $academicVP->id,
                'is_active' => true,
                'sort_order' => 9,
            ]
        );

        Department::firstOrCreate(
            ['code' => 'CNS'],
            [
                'name' => 'College of Natural Sciences',
                'description' => 'Biology, chemistry, physics, mathematics, computational sciences',
                'parent_id' => $academicVP->id,
                'is_active' => true,
                'sort_order' => 10,
            ]
        );
    }
}
