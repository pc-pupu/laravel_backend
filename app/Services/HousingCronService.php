<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Port of Drupal cronjobs module (hook_cron).
 */
class HousingCronService
{
    public function run(): array
    {
        $log = [];
        $log[] = $this->deactivateStaleDraftApplicants();
        $log = array_merge($log, $this->cancelUnacceptedAllotments('NA', 'housing_new_allotment_application', 'New Allotment'));
        $log = array_merge($log, $this->cancelUnacceptedAllotments('VS', 'housing_vs_application', 'Vertical Shifting'));
        $log = array_merge($log, $this->cancelUnacceptedAllotments('CS', 'housing_cs_application', 'Category Shifting'));
        $log = array_merge($log, $this->sendAllotmentReminderEmails(14, 'Reminder for Offer of Allotment Accept', 15));
        $log = array_merge($log, $this->sendAllotmentReminderEmails(24, 'Final Reminder for Offer of Allotment Accept', 5));
        return $log;
    }

    private function deactivateStaleDraftApplicants(): string
    {
        $uids = DB::table('users as u')
            ->join('user_role as ur', 'ur.uid', '=', 'u.uid')
            ->leftJoin('housing_applicant_official_detail as haod', 'haod.uid', '=', 'u.uid')
            ->leftJoin('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('u.status', 1)
            ->where('ur.rid', 4)
            ->whereRaw('COALESCE(u.created_at, u.updated_at, NOW()) < NOW() - INTERVAL \'30 days\'')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('hoa.status', 'draft')
                        ->where('hoa.application_no', 'like', 'NA-%');
                })->orWhereNull('hoa.status');
            })
            ->distinct()
            ->pluck('u.uid');

        if ($uids->isEmpty()) {
            return 'No stale draft applicants deactivated.';
        }

        DB::table('users')->whereIn('uid', $uids)->update(['status' => 0]);

        return 'Deactivated user ids: ' . $uids->implode(',');
    }

    private function cancelUnacceptedAllotments(string $label, string $appTable, string $logPrefix): array
    {
        $joinCol = $appTable === 'housing_new_allotment_application' ? 'hnaa' : ($appTable === 'housing_vs_application' ? 'hva' : 'hca');

        $rows = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join("{$appTable} as {$joinCol}", "{$joinCol}.online_application_id", '=', 'hoa.online_application_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->where('hoa.status', 'allotted_approved')
            ->whereRaw('hfo.allotment_approve_or_reject_date < NOW() - INTERVAL \'30 days\'')
            ->whereNull('hfo.accept_reject_status')
            ->select('hoa.online_application_id', 'hfo.flat_id')
            ->get();

        if ($rows->isEmpty()) {
            return ["{$logPrefix}: No applications cancelled.", "{$logPrefix}: No flats released."];
        }

        $appIds = $rows->pluck('online_application_id')->unique()->values();
        $flatIds = $rows->pluck('flat_id')->unique()->values();

        DB::table('housing_flat_occupant')
            ->whereIn('online_application_id', $appIds)
            ->update(['accept_reject_status' => 'Cancel']);

        DB::table('housing_flat')->whereIn('flat_id', $flatIds)->update(['flat_status_id' => 1]);

        return [
            "{$logPrefix}: Cancelled application ids: " . $appIds->implode(','),
            "{$logPrefix}: Released flat ids: " . $flatIds->implode(','),
        ];
    }

    private function sendAllotmentReminderEmails(int $daysAgo, string $subject, int $daysLeft): array
    {
        $messages = [];
        foreach (['housing_new_allotment_application' => 'New Allotment', 'housing_vs_application' => 'Vertical Shifting', 'housing_cs_application' => 'Category Shifting'] as $table => $prefix) {
            $alias = match ($table) {
                'housing_new_allotment_application' => 'hnaa',
                'housing_vs_application' => 'hva',
                default => 'hca',
            };

            $emails = DB::table('housing_applicant_official_detail as haod')
                ->join('users as u', 'u.uid', '=', 'haod.uid')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join("{$table} as {$alias}", "{$alias}.online_application_id", '=', 'hoa.online_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->where('hoa.status', 'allotted_approved')
                ->whereRaw('hfo.allotment_approve_or_reject_date::date = current_date - ?::interval', ["{$daysAgo} days"])
                ->whereNull('hfo.accept_reject_status')
                ->whereNotNull('u.mail')
                ->pluck('u.mail')
                ->filter()
                ->unique();

            if ($emails->isEmpty()) {
                $messages[] = "{$prefix} ({$daysAgo}d): No reminder emails sent.";
                continue;
            }

            $body = "<html><body>Dear Applicant,<br><br>"
                . "We want to remind you that a flat has been allotted to you and {$daysLeft} days are left to accept the offer of allotment. "
                . "Please login to your account and accept the Offer of Allotment within next {$daysLeft} days to avoid cancellation of the flat allotment."
                . "<br><br>Regards,<br>Housing Department<br>Government of West Bengal</body></html>";

            $notification = app(NotificationService::class);
            foreach ($emails as $email) {
                try {
                    $notification->sendMail($email, $subject, $body);
                } catch (\Throwable $e) {
                    Log::warning('Allotment reminder email failed', ['email' => $email, 'error' => $e->getMessage()]);
                }
            }

            $messages[] = "{$prefix} ({$daysAgo}d): Sent " . $emails->count() . ' emails.';
        }

        return $messages;
    }
}
