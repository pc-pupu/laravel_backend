<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Port of Drupal auto_updation module cron callbacks.
 */
class AutoUpdationService
{
    public function runAll(): array
    {
        return array_merge(
            $this->runOfferCancellation(),
            $this->runOfferAfterExtension(),
            $this->runLicenseCancellation(),
            $this->runLicenseAfterExtension(),
            $this->runTransferChecking()
        );
    }

    public function runOfferCancellation(): array
    {
        $apps = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->where('hoa.status', 'housing_official_approved')
            ->whereNotNull('hoa.date_of_verified')
            ->select('hoa.*', 'haod.uid')
            ->get();

        $count = 0;
        foreach ($apps as $app) {
            if ($this->daysSince($app->date_of_verified) <= 15) {
                continue;
            }
            $this->applyCancellation($app, 'offer_letter_cancel', 'offer-letter', (int) $app->uid);
            $count++;
        }

        return ['offer_cancellation: ' . $count . ' applications updated.'];
    }

    public function runOfferAfterExtension(): array
    {
        $apps = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->where('hoa.status', 'offer_letter_extended')
            ->whereNotNull('hoa.date_of_verified')
            ->select('hoa.*', 'haod.uid')
            ->get();

        $count = 0;
        foreach ($apps as $app) {
            if ($this->daysSince($app->date_of_verified) <= 15) {
                continue;
            }
            $this->applyCancellation($app, 'offer_letter_cancel', 'offer-letter', (int) $app->uid);
            $count++;
        }

        return ['offer_after_extension: ' . $count . ' applications updated.'];
    }

    public function runLicenseCancellation(): array
    {
        $apps = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_occupant_license as hol', 'hol.flat_occupant_id', '=', 'hfo.flat_occupant_id')
            ->where('hoa.status', 'license_generate')
            ->whereNotNull('hoa.date_of_verified')
            ->select('hoa.*', 'haod.uid', 'hol.possession_date')
            ->orderBy('hoa.online_application_id')
            ->get();

        $count = 0;
        foreach ($apps as $app) {
            if ($this->daysSince($app->date_of_verified) <= 15 || !empty($app->possession_date)) {
                continue;
            }
            $this->applyCancellation($app, 'license_cancel', 'license', (int) $app->uid);
            $count++;
        }

        return ['license_cancellation: ' . $count . ' applications updated.'];
    }

    public function runLicenseAfterExtension(): array
    {
        $apps = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_occupant_license as hol', 'hol.flat_occupant_id', '=', 'hfo.flat_occupant_id')
            ->where('hoa.status', 'license_extended')
            ->whereNotNull('hoa.date_of_verified')
            ->select('hoa.*', 'haod.uid', 'hol.possession_date')
            ->get();

        $count = 0;
        foreach ($apps as $app) {
            if ($this->daysSince($app->date_of_verified) <= 15 || !empty($app->possession_date)) {
                continue;
            }
            $this->applyCancellation($app, 'license_cancel', 'license', (int) $app->uid);
            $count++;
        }

        return ['license_after_extension: ' . $count . ' applications updated.'];
    }

    public function runTransferChecking(): array
    {
        // Monthly DDO transfer check placeholder — logs applicants needing review.
        $count = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('haod.is_active', 1)
            ->whereIn('hoa.status', ['applied', 'verified', 'housing_official_approved'])
            ->count();

        return ['transfer_checking: ' . $count . ' active applications (manual DDO review if HRMS changed).'];
    }

    private function applyCancellation(object $app, string $shortCode, string $cancellationType, int $uid): void
    {
        DB::beginTransaction();
        try {
            DB::table('housing_online_application')
                ->where('online_application_id', $app->online_application_id)
                ->update([
                    'status' => $shortCode,
                    'date_of_verified' => now(),
                ]);

            ProcessFlowService::insertProcessFlow($app->online_application_id, $shortCode, 1);

            DB::table('housing_auto_cancellation')->insert([
                'uid' => $uid,
                'online_application_id' => $app->online_application_id,
                'cancellation_type' => $cancellationType,
            ]);

            DB::table('housing_flat_occupant')
                ->where('online_application_id', $app->online_application_id)
                ->update([
                    'cancellation_extension_status' => $shortCode,
                    'cancellation_extension_date' => now(),
                ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Auto cancellation failed', [
                'application_id' => $app->online_application_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function daysSince($date): int
    {
        if (!$date) {
            return 0;
        }

        return Carbon::parse($date)->diffInDays(now());
    }
}
