<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HrmsApplicantLoginLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample dummy data based on Drupal structure
        $dummyData = [
            [
                'hrms_id' => '2022014359',
                'json_decrypted_data' => json_encode([
                    'src' => 'HRMS',
                    'hrmsid' => '2022014359',
                    'email' => 'poulami.diti@gmail.com',
                    'mobile' => '7044216616',
                    'name' => 'DR POULAMI BANERJEE',
                    'designation' => 'General Duty Medical Officer',
                    'status' => 'authenticated',
                    'sysTimeStamp' => '26/11/2025 09:42:49'
                ]),
                'json_encrypted_data' => '', // Will be empty for dummy data
                'created_at' => Carbon::now()->subDays(5),
            ],
            [
                'hrms_id' => '2000007780',
                'json_decrypted_data' => json_encode([
                    'src' => 'HRMS',
                    'hrmsid' => '2000007780',
                    'email' => 'JEETENDRAGUPTA@gmail.com',
                    'mobile' => '7797660379',
                    'name' => 'JEETENDRA GUPTA',
                    'designation' => 'Additional District & Sessions Judge',
                    'status' => 'authenticated',
                    'sysTimeStamp' => '12/06/2025 04:11:00'
                ]),
                'json_encrypted_data' => '',
                'created_at' => Carbon::now()->subDays(10),
            ],
            [
                'hrms_id' => '1995002970',
                'json_decrypted_data' => json_encode([
                    'src' => 'HRMS',
                    'hrmsid' => '1995002970',
                    'email' => 'test@example.com',
                    'mobile' => '9999999999',
                    'name' => 'JOHN DOE',
                    'designation' => 'Scientific Officer/Engineer-SB',
                    'status' => 'authenticated',
                    'sysTimeStamp' => date('d/m/Y H:i:s')
                ]),
                'json_encrypted_data' => '',
                'created_at' => Carbon::now()->subDays(2),
            ],
        ];

        // Insert dummy data
        foreach ($dummyData as $data) {
            DB::table('housing_hrms_applicant_login_log')->insert($data);
        }

        $this->command->info('Dummy HRMS Applicant Login Log data seeded successfully!');
    }
}
