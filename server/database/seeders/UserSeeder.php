<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $op = Department::where('code', 'OP')->first();
        $aad = Department::where('code', 'AAD')->first();
        $rad = Department::where('code', 'RAD')->first();
        $ictc = Department::where('code', 'ICTC')->first();
        $cmhs = Department::where('code', 'CMHS')->first();

        $defaultPassword = Hash::make('password123');

        $emailDomain = 'wollo.edu.et';
        $acronym = 'WU';

        // 1. Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => "superadmin@{$emailDomain}"],
            [
                'name' => 'Prof. Mengesha Worku',
                'password' => $defaultPassword,
                'phone' => '+251911000001',
                'position' => 'Executive Head / President',
                'employee_id' => "{$acronym}-EMP-0001",
                'department_id' => $op?->id,
                'is_active' => true,
            ]
        );
        $superAdmin->syncRoles(['Super Admin']);
        if ($op) {
            $op->update(['head_user_id' => $superAdmin->id]);
        }

        // 2. Admin
        $admin = User::firstOrCreate(
            ['email' => "admin@{$emailDomain}"],
            [
                'name' => 'Dr. Birhanu Alemu',
                'password' => $defaultPassword,
                'phone' => '+251911000002',
                'position' => 'ICT Director & System Administrator',
                'employee_id' => "{$acronym}-EMP-0002",
                'department_id' => $ictc?->id,
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['Admin']);
        if ($ictc) {
            $ictc->update(['head_user_id' => $admin->id]);
        }

        // 3. Department Head (Academic Affairs)
        $headAad = User::firstOrCreate(
            ['email' => "head.aad@{$emailDomain}"],
            [
                'name' => 'Dr. Tigist Haile',
                'password' => $defaultPassword,
                'phone' => '+251911000003',
                'position' => 'Academic Affairs Director',
                'employee_id' => "{$acronym}-EMP-0003",
                'department_id' => $aad?->id,
                'is_active' => true,
            ]
        );
        $headAad->syncRoles(['Department Head']);
        if ($aad) {
            $aad->update(['head_user_id' => $headAad->id]);
        }

        // 4. Document Controller
        $docController = User::firstOrCreate(
            ['email' => "doc.controller@{$emailDomain}"],
            [
                'name' => 'Ato Dawit Kassa',
                'password' => $defaultPassword,
                'phone' => '+251911000004',
                'position' => 'Senior Document Controller & Cataloger',
                'employee_id' => "{$acronym}-EMP-0004",
                'department_id' => $rad?->id,
                'is_active' => true,
            ]
        );
        $docController->syncRoles(['Document Controller']);

        // 5. Records Officer
        $recordsOfficer = User::firstOrCreate(
            ['email' => "records.officer@{$emailDomain}"],
            [
                'name' => 'W/ro Selamawit Bekele',
                'password' => $defaultPassword,
                'phone' => '+251911000005',
                'position' => 'Central Records & Registry Officer',
                'employee_id' => "{$acronym}-EMP-0005",
                'department_id' => $op?->id,
                'is_active' => true,
            ]
        );
        $recordsOfficer->syncRoles(['Records Officer']);

        // 6. Archivist
        $archivist = User::firstOrCreate(
            ['email' => "archivist@{$emailDomain}"],
            [
                'name' => 'Ato Solomon Tadesse',
                'password' => $defaultPassword,
                'phone' => '+251911000006',
                'position' => 'Chief Archivist',
                'employee_id' => "{$acronym}-EMP-0006",
                'department_id' => $rad?->id,
                'is_active' => true,
            ]
        );
        $archivist->syncRoles(['Archivist']);

        // 7. General Staff member
        $staff = User::firstOrCreate(
            ['email' => "staff.ict@{$emailDomain}"],
            [
                'name' => 'W/ro Aster Kebede',
                'password' => $defaultPassword,
                'phone' => '+251911000007',
                'position' => 'Systems Support Officer',
                'employee_id' => "{$acronym}-EMP-0007",
                'department_id' => $ictc?->id,
                'is_active' => true,
            ]
        );
        $staff->syncRoles(['Staff']);

        // Standard institutional aliases for Wollo University
        $aliases = [
                ['president@wu.edu.et', 'Prof. Mengesha Worku', '+251911000011', 'University President', 'WU-EMP-0011', $op?->id, 'Super Admin'],
                ['admin@wu.edu.et', 'Dr. Birhanu Alemu', '+251911000012', 'ICT Director & System Administrator', 'WU-EMP-0012', $ictc?->id, 'Admin'],
                ['academic.director@wu.edu.et', 'Dr. Tigist Haile', '+251911000013', 'Academic Affairs Director', 'WU-EMP-0013', $aad?->id, 'Department Head'],
                ['controller@wu.edu.et', 'Ato Dawit Kassa', '+251911000014', 'Senior Document Controller & Cataloger', 'WU-EMP-0014', $rad?->id, 'Document Controller'],
                ['records@wu.edu.et', 'W/ro Selamawit Bekele', '+251911000015', 'Central Records & Registry Officer', 'WU-EMP-0015', $op?->id, 'Records Officer'],
                ['archivist@wu.edu.et', 'Ato Solomon Tadesse', '+251911000016', 'Chief University Archivist', 'WU-EMP-0016', $rad?->id, 'Archivist'],
                ['staff.medicine@wu.edu.et', 'Dr. Amanuel Girma', '+251911000017', 'Assistant Professor', 'WU-EMP-0017', $cmhs?->id, 'Staff'],
            ];

            foreach ($aliases as [$email, $name, $phone, $pos, $empId, $deptId, $role]) {
                $u = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => $defaultPassword,
                        'phone' => $phone,
                        'position' => $pos,
                        'employee_id' => $empId,
                        'department_id' => $deptId,
                        'is_active' => true,
                    ]
                );
                $u->syncRoles([$role]);
            }
    }
}
