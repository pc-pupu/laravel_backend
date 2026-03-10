<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\ErrorLogService;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationService
{
    /**
     * Send email notification
     * Equivalent to custom_mail_function in Drupal
     */
    public function sendMail($to, $subject, $message, $name = 'RHE', $otp = '')
    {
        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            
            if ($otp == 'otp') {
                $mail->Host = '164.100.2.239'; // https://otprelay.nic.in
            } else {
                $mail->Host = '164.100.13.55'; // https://smtpsgwhyd.nic.in
            }
            
            $mail->Port = 465;
            $mail->SMTPSecure = 'tls';
            $mail->SMTPAuth = true;
            $mail->Username = 'noreply-eallotment@bangla.gov.in';
            $mail->Password = 'G2#pS2@uW7';
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = 'html';

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Sender & recipient
            $mail->setFrom('noreply-eallotment@bangla.gov.in', 'Noreply e-Allotment');
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = '';

            $result = $mail->send();
            
            if ($result) {
                Log::info('Email sent successfully', ['to' => $to, 'subject' => $subject]);
                return true;
            } else {
                Log::error('Email send failed', ['to' => $to, 'error' => $mail->ErrorInfo]);
                return false;
            }

        } catch (Exception $e) {
            Log::error('Email Exception', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            ErrorLogService::logException($e, 'error', ['module' => 'notifications', 'action' => 'send_mail', 'to' => $to]);
            return false;
        }
    }

    /**
     * Send SMS notification
     * Equivalent to custom_sms_function in Drupal
     */
    public function sendSms($dest, $msg, $templateId = '')
    {
        try {
            $uid = 'wbhousing.sms';
            $pass = 'bJoddiHP';
            $send = 'RHE';
            $url = "https://smsgw.sms.gov.in/failsafe/HttpLink?";
            
            $data = "username=$uid&pin=$pass&message=$msg&mnumber=$dest&signature=$send&dlt_entity_id=1101589480000043999&dlt_template_id=$templateId";

            $response = Http::timeout(30)
                ->withOptions([
                    'verify' => false,
                ])
                ->asForm()
                ->post($url, [
                    'username' => $uid,
                    'pin' => $pass,
                    'message' => $msg,
                    'mnumber' => $dest,
                    'signature' => $send,
                    'dlt_entity_id' => '1101589480000043999',
                    'dlt_template_id' => $templateId,
                ]);

            if ($response->successful()) {
                Log::info('SMS sent successfully', ['dest' => $dest, 'template_id' => $templateId]);
                return true;
            } else {
                Log::error('SMS send failed', ['dest' => $dest, 'response' => $response->body()]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('SMS Exception', [
                'dest' => $dest,
                'error' => $e->getMessage(),
            ]);
            ErrorLogService::logException($e, 'error', ['module' => 'notifications', 'action' => 'send_sms', 'dest' => $dest]);
            return false;
        }
    }

    /**
     * Send rejection notification (email + SMS)
     */
    public function sendRejectionNotification($applicationId, $applicantName, $email, $mobileNo)
    {
        $subject = 'Application Rejected';
        $message = "Dear $applicantName, your application for RHE has been rejected by competent authority. For more details, you may please log in to rhe.wb.gov.in\n-Dept. of Housing, GoWB";
        $templateId = '1107175508616693124';

        $emailSent = $this->sendMail($email, $subject, $message);
        $smsSent = $this->sendSms($mobileNo, $message, $templateId);

        return ['email' => $emailSent, 'sms' => $smsSent];
    }

    /**
     * Send license generation notification (email + SMS)
     */
    public function sendLicenseGenerationNotification($applicationId, $applicantName, $email, $mobileNo, $licenseNo, $applicationNo)
    {
        $subject = 'License Generated Successfully';
        $message = "Dear $applicantName, the Licence for your allotted RHE has been successfully generated. You may please log in to view or download the Licence.\n-Dept. of Housing, GoWB";
        $templateId = '1107175508672559576';

        $emailSent = $this->sendMail($email, $subject, $message);
        $smsSent = $this->sendSms($mobileNo, $message, $templateId);

        return ['email' => $emailSent, 'sms' => $smsSent];
    }
}

