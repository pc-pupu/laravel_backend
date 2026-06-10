<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplicantDataUploadRequest;
use App\Services\ErrorLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ApplicantDataUploadController extends Controller
{
    /**
     * Expected Excel columns in order:
     */
    private const EXCEL_COLUMNS = [
        'applicant_name', 'guardian_name', 'dob', 'gender', 'designation',
        'pay_band', 'pay_in_band', 'posting_place', 'dor', 'office_name',
        'office_street', 'office_city', 'pincode', 'ddo_id', 'doa',
        'serial_no', 'remarks', 'flat_type', 'reason'
    ];

    /**
     * Upload and process applicant data from Excel file.
     */
    public function upload(ApplicantDataUploadRequest $request)
    {
        try {
            $file = $request->file('file');
            $skipExisting = $request->boolean('skip_existing', false);

            // Read Excel file
            $data = $this->readExcelFile($file);

            if (empty($data)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Excel file is empty or invalid format.',
                ], 422);
            }

            DB::beginTransaction();

            $created = 0;
            $skipped = 0;
            $errors = [];

            foreach ($data as $rowIndex => $row) {
                try {
                    // Skip if required fields missing
                    if (empty($row['applicant_name']) || empty($row['dob'])) {
                        $skipped++;
                        continue;
                    }

                    // Drupal bulk upload did not include HRMS ID. DDO ID is not a unique applicant key.
                    if ($skipExisting && !empty($row['serial_no']) && DB::table('housing_online_application')->where('computer_serial_no', $row['serial_no'])->exists()) {
                        $skipped++;
                        continue;
                    }

                    // Generate username
                    $username = $this->generateUniqueUsername($row['applicant_name']);

                    // Create user in the legacy schema used by the rest of the migration.
                    $uid = DB::table('users')->insertGetId([
                        'name' => $username,
                        'mail' => null,
                        'password' => Hash::make($username),
                        'password_old' => Hash::make($username),
                        'status' => 1,
                        'new_pass_set' => 1,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ], 'uid');

                    // Assign 'Applicant' role
                    DB::table('user_role')->insert([
                        'uid' => $uid,
                        'rid' => 4, // Applicant role
                    ]);

                    // Create housing applicant
                    $housingApplicantId = DB::table('housing_applicant')->insertGetId([
                        'uid' => $uid,
                        'applicant_name' => strtoupper(trim($row['applicant_name'])),
                        'guardian_name' => trim($row['guardian_name'] ?? ''),
                        'date_of_birth' => $this->parseDate($row['dob']),
                        'gender' => strtoupper(trim($row['gender'] ?? '')),
                    ], 'housing_applicant_id');

                    // Create official details
                    $officialDetailId = DB::table('housing_applicant_official_detail')->insertGetId([
                        'uid' => $uid,
                        'housing_applicant_id' => $housingApplicantId,
                        'applicant_designation' => strtoupper(trim($row['designation'] ?? '')),
                        'pay_band_id' => trim($row['pay_band'] ?? ''),
                        'pay_in_the_pay_band' => trim($row['pay_in_band'] ?? ''),
                        'applicant_posting_place' => strtoupper(trim($row['posting_place'] ?? '')),
                        'date_of_retirement' => $this->parseDate($row['dor']),
                        'office_name' => strtoupper(trim($row['office_name'] ?? '')),
                        'office_street' => strtoupper(trim($row['office_street'] ?? '')),
                        'office_city_town_village' => strtoupper(trim($row['office_city'] ?? '')),
                        'office_pin_code' => trim($row['pincode'] ?? ''),
                        'ddo_id' => $row['ddo_id'] ?? '',
                    ], 'applicant_official_detail_id');

                    // Create online application (marked as verified for bulk upload)
                    $dateOfApplication = $this->parseDate($row['doa']);
                    $onlineApplicationId = DB::table('housing_online_application')->insertGetId([
                        'applicant_official_detail_id' => $officialDetailId,
                        'status' => 'verified',
                        'date_of_application' => $dateOfApplication,
                        'is_backlog_applicant' => 1,
                        'computer_serial_no' => trim($row['serial_no'] ?? ''),
                        'remarks' => trim($row['remarks'] ?? '') ?: null,
                    ], 'online_application_id');

                    DB::table('housing_online_application')
                        ->where('online_application_id', $onlineApplicationId)
                        ->update([
                            'application_no' => 'NA-' . str_replace('-', '', (string) $dateOfApplication) . '-' . $onlineApplicationId,
                        ]);

                    // Create new allotment application
                    DB::table('housing_new_allotment_application')->insert([
                        'online_application_id' => $onlineApplicationId,
                        'flat_type_id' => $this->getFlatTypeId($row['flat_type'] ?? ''),
                        'allotment_category' => trim($row['reason'] ?? ''),
                    ]);

                    $created++;

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data upload completed.',
                'summary' => [
                    'created' => $created,
                    'skipped' => $skipped,
                    'errors' => count($errors) > 0 ? $errors : null,
                ]
            ], 200);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            ErrorLogService::logException($e, 'error', ['module' => 'applicant_data_upload', 'action' => 'upload']);
            
            return response()->json([
                'status' => 'error',
                'message' => 'File upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Read Excel file and return data as associative array.
     * 
     * Supports .xls and .xlsx formats using built-in PHP functions.
     */
    private function readExcelFile($file): array
    {
        $path = $file->store('temp');
        $fullPath = storage_path('app/' . $path);

        try {
            $data = [];

            // Try using SimpleXML for XLSX
            if ($file->getClientOriginalExtension() === 'xlsx') {
                $data = $this->readXlsxFile($fullPath);
            } else {
                // For XLS, use a simpler approach or PHPExcel if available
                $data = $this->readXlsFile($fullPath);
            }

            return $data;
        } finally {
            @unlink($fullPath);
        }
    }

    /**
     * Parse XLSX file using SimpleXML.
     */
    private function readXlsxFile(string $filePath): array
    {
        $data = [];
        
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $sharedStrings = $this->readSharedStrings($zip);
                $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                $zip->close();

                $xmlObject = simplexml_load_string($xml);
                $rows = $xmlObject->sheetData->row;

                foreach ($rows as $row) {
                    $rowData = [];
                    $colIndex = 0;
                    
                    foreach ($row->c as $cell) {
                        $value = '';
                        if (isset($cell->v)) {
                            $value = (string)$cell->v;
                            if ((string) ($cell['t'] ?? '') === 's') {
                                $value = $sharedStrings[(int) $value] ?? $value;
                            }
                        }
                        if (isset(self::EXCEL_COLUMNS[$colIndex])) {
                            $rowData[self::EXCEL_COLUMNS[$colIndex]] = $value;
                        }
                        $colIndex++;
                    }
                    
                    if (!empty($rowData)) {
                        $data[] = $rowData;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback to CSV parsing
            return $this->readAsCSV($filePath);
        }

        return $data;
    }

    /**
     * Parse XLS file (basic implementation).
     */
    private function readXlsFile(string $filePath): array
    {
        // Native XLS parsing needs a binary Excel reader package. Keep the failure explicit
        // so admins do not get a misleading "0 inserted" result.
        return [];
    }

    /**
     * Fallback: Read file as CSV.
     */
    private function readAsCSV(string $filePath): array
    {
        $data = [];
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                $rowData = [];
                foreach ($row as $colIndex => $value) {
                    if (isset(self::EXCEL_COLUMNS[$colIndex])) {
                        $rowData[self::EXCEL_COLUMNS[$colIndex]] = $value;
                    }
                }
                if (!empty($rowData)) {
                    $data[] = $rowData;
                }
            }
            fclose($handle);
        }

        return $data;
    }

    /**
     * Generate unique username.
     */
    private function generateUniqueUsername(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (!empty($parts) && in_array(strtolower(rtrim($parts[0], '.')), ['dr'], true)) {
            array_shift($parts);
        }

        $base = strtolower(substr($parts[0] ?? 'usr', 0, 3));
        if (count($parts) > 1) {
            $base .= strtolower(substr($parts[1], 0, 3));
        }
        $base = str_replace('.', '', $base);

        do {
            $username = $base . random_int(1, 100000);
        } while (DB::table('users')->where('name', $username)->exists());

        return $username;
    }

    /**
     * Parse date from various formats.
     */
    private function parseDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        // Try common date formats
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'm/d/Y'];
        
        foreach ($formats as $format) {
            try {
                $date = \DateTime::createFromFormat($format, $dateStr);
                if ($date) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    private function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        $xmlObject = simplexml_load_string($xml);
        foreach ($xmlObject->si ?? [] as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r ?? [] as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function getFlatTypeId(string $flatType): int
    {
        return (int) (DB::table('housing_flat_type')
            ->where('flat_type', trim($flatType))
            ->value('flat_type_id') ?? 0);
    }
}
