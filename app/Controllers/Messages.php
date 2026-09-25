<?php

namespace App\Controllers;

use App\Libraries\Sms_lib;
use App\Models\Customer;
use App\Models\Person;
use CodeIgniter\HTTP\ResponseInterface;

class Messages extends Secure_Controller
{
    private Sms_lib $sms_lib;

    public function __construct()
    {
        parent::__construct('messages');
        $this->sms_lib = new Sms_lib();
    }

    /**
     * @return string
     */
    public function getIndex(): string
    {
        $customer = model(Customer::class);
        $all_customers = $customer->get_all()->getResult();

        $customer_list = [];
        $unique_phones = [];
        $unique_emails = [];

        foreach ($all_customers as $c) {
            $name = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
            if (empty($name) && !empty($c->company_name)) {
                $name = trim($c->company_name);
            }
            if (empty($name)) {
                $name = 'Customer #' . $c->person_id;
            }

            $phone = trim($c->phone_number ?? '');
            $email = trim($c->email ?? '');

            if (!empty($phone)) {
                $unique_phones[] = $phone;
            }
            if (!empty($email)) {
                $unique_emails[] = $email;
            }

            $customer_list[] = [
                'person_id' => (int)$c->person_id,
                'name'      => $name,
                'phone'     => $phone,
                'email'     => $email,
            ];
        }

        $data = [
            'meta_marketing_template' => $this->config['meta_marketing_template'] ?? 'taz_market',
            'whatsapp_provider'       => $this->config['whatsapp_api_provider'] ?? 'meta',
            'customer_list'           => $customer_list,
            'selected_customer_ids'   => [],
            'all_customer_phones'     => implode(', ', array_values(array_unique($unique_phones))),
            'all_customer_emails'     => implode(', ', array_values(array_unique($unique_emails))),
            'customer_count'          => count($customer_list),
            'phone'                   => ''
        ];

        return view('messages/sms', $data);
    }

    /**
     * @param int $person_id
     * @return string
     */
    public function getView(int $person_id = NEW_ENTRY): string
    {
        $person = model(Person::class);
        $info = $person->get_info($person_id);

        foreach (get_object_vars($info) as $property => $value) {
            $info->$property = $value;
        }
        $data['person_info'] = $info;

        return view('messages/form_sms', $data);
    }

    /**
     * Sends bulk marketing/notification messages.
     *
     * @return ResponseInterface
     */
    public function postSend(): ResponseInterface
    {
        $raw_phone     = (string)$this->request->getPost('phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $message       = trim((string)$this->request->getPost('message', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $message_type  = (string)$this->request->getPost('message_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $template_name = trim((string)$this->request->getPost('template_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $subject       = (string)$this->request->getPost('subject', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'Message from ' . (config('OSPOS')->settings['company'] ?? 'Store');

        $recipients = array_values(array_unique(array_filter(array_map('trim', explode(',', $raw_phone)))));

        if (empty($recipients)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Please select or enter at least one recipient.'
            ]);
        }

        $attachment_path = null;
        $attachment = $this->request->getFile('attachment');

        if ($attachment && $attachment->isValid() && !$attachment->hasMoved()) {
            $newName = $attachment->getRandomName();
            $attachment->move(FCPATH . 'uploads/whatsapp_media', $newName);
            $attachment_path = FCPATH . 'uploads/whatsapp_media/' . $newName;
        }

        $total = count($recipients);
        $success_count = 0;
        $fail_count = 0;

        if ($message_type === 'whatsapp') {
            $whatsapp = new \App\Libraries\WhatsappLib();
            $provider = $this->config['whatsapp_api_provider'] ?? 'meta';
            $marketing_template = !empty($template_name) ? $template_name : ($this->config['meta_marketing_template'] ?? 'taz_market');

            if ($provider === 'meta' && !empty($marketing_template)) {
                $media_id = null;
                $header_type = 'image';
                $filename = '';

                // 1. Check if user attached media
                if ($attachment_path && file_exists($attachment_path)) {
                    $ext = strtolower(pathinfo($attachment_path, PATHINFO_EXTENSION));
                    $header_type = in_array($ext, ['pdf', 'doc', 'docx']) ? 'document' : 'image';
                    $filename = basename($attachment_path);
                    try {
                        $media_id = $whatsapp->uploadMediaFile($attachment_path);
                    } catch (\Exception $e) {
                        log_message('error', 'Error uploading attachment: ' . $e->getMessage());
                    }
                }

                // 2. If no attachment uploaded but template is taz_market (requires an IMAGE header)
                if (empty($media_id) && $marketing_template === 'taz_market') {
                    $candidate_images = [
                        FCPATH . 'uploads/graff.png',
                        FCPATH . 'images/logo.png',
                        FCPATH . 'images/logowithouttext256.png',
                        FCPATH . 'images/enp.PNG'
                    ];
                    foreach ($candidate_images as $imgPath) {
                        if (file_exists($imgPath)) {
                            try {
                                $media_id = $whatsapp->uploadMediaFile($imgPath);
                                $header_type = 'image';
                                $filename = basename($imgPath);
                                break;
                            } catch (\Exception $e) {
                                log_message('error', 'Error uploading fallback image: ' . $e->getMessage());
                            }
                        }
                    }
                }

                // 3. Body parameter (taz_market requires {{1}})
                $body_param = !empty($message) ? $message : 'Valued Customer';

                foreach ($recipients as $recipient_phone) {
                    if (!empty($media_id)) {
                        $res = $whatsapp->sendTemplateWithMediaId(
                            $recipient_phone,
                            $marketing_template,
                            $media_id,
                            $filename,
                            [$body_param],
                            $header_type
                        );
                    } else {
                        $res = $whatsapp->sendTemplate(
                            $recipient_phone,
                            $marketing_template,
                            null,
                            null,
                            null,
                            [$body_param]
                        );
                    }

                    if ($res) {
                        $success_count++;
                    } else {
                        $fail_count++;
                    }

                    if ($total > 1) {
                        usleep(150000); // 150ms throttle
                    }
                }
            } else {
                // Free-form message via Twilio or Meta text
                foreach ($recipients as $recipient_phone) {
                    $res = $whatsapp->sendMessage($recipient_phone, $message ?: 'Hello from our store!');
                    if ($res) {
                        $success_count++;
                    } else {
                        $fail_count++;
                    }
                }
            }

            if ($success_count > 0) {
                $msg = "WhatsApp sent successfully to {$success_count} of {$total} recipient(s).";
                if ($fail_count > 0) {
                    $msg .= " ({$fail_count} failed)";
                }
                return $this->response->setJSON(['success' => true, 'message' => $msg]);
            } else {
                $lastErr = $whatsapp->getLastError();
                $errDetail = $lastErr ? " Meta Error: {$lastErr}" : " Please check your Meta API credentials and phone numbers.";
                return $this->response->setJSON([
                    'success' => false,
                    'message' => "Failed to send WhatsApp message.{$errDetail}"
                ]);
            }

        } elseif ($message_type === 'email') {
            $email_lib = new \App\Libraries\Email_lib();
            foreach ($recipients as $email) {
                $res = $email_lib->sendEmail($email, $subject, $message, $attachment_path);
                if ($res) {
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }

            if ($success_count > 0) {
                $msg = "Email sent successfully to {$success_count} of {$total} recipient(s).";
                if ($fail_count > 0) {
                    $msg .= " ({$fail_count} failed)";
                }
                return $this->response->setJSON(['success' => true, 'message' => $msg]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to send email to recipients. Check your email/SMTP settings.'
                ]);
            }

        } else {
            // SMS
            foreach ($recipients as $recipient_phone) {
                $res = $this->sms_lib->sendSMS($recipient_phone, $message);
                if ($res) {
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }

            if ($success_count > 0) {
                $msg = "SMS sent successfully to {$success_count} of {$total} recipient(s).";
                if ($fail_count > 0) {
                    $msg .= " ({$fail_count} failed)";
                }
                return $this->response->setJSON(['success' => true, 'message' => $msg]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to send SMS to recipients. Check your SMS provider settings.'
                ]);
            }
        }
    }

    /**
     * Sends a single message from the customer modal form.
     *
     * @param int $person_id
     * @return ResponseInterface
     * @noinspection PhpUnused
     */
    public function postSendForm(int $person_id = NEW_ENTRY): ResponseInterface
    {
        $phone         = (string)$this->request->getPost('phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $message       = trim((string)$this->request->getPost('message', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $message_type  = (string)$this->request->getPost('message_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $template_name = trim((string)$this->request->getPost('template_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $subject       = (string)$this->request->getPost('subject', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'Message from ' . (config('OSPOS')->settings['company'] ?? 'Store');

        $attachment_path = null;
        $attachment = $this->request->getFile('attachment');

        if ($attachment && $attachment->isValid() && !$attachment->hasMoved()) {
            $newName = $attachment->getRandomName();
            $attachment->move(FCPATH . 'uploads/whatsapp_media', $newName);
            $attachment_path = FCPATH . 'uploads/whatsapp_media/' . $newName;
        }

        if ($message_type === 'whatsapp') {
            $whatsapp = new \App\Libraries\WhatsappLib();
            $provider = $this->config['whatsapp_api_provider'] ?? 'meta';
            $marketing_template = !empty($template_name) ? $template_name : ($this->config['meta_marketing_template'] ?? 'taz_market');

            if ($provider === 'meta' && !empty($marketing_template)) {
                $media_id = null;
                $header_type = 'image';
                $filename = '';

                if ($attachment_path && file_exists($attachment_path)) {
                    $ext = strtolower(pathinfo($attachment_path, PATHINFO_EXTENSION));
                    $header_type = in_array($ext, ['pdf', 'doc', 'docx']) ? 'document' : 'image';
                    $filename = basename($attachment_path);
                    try {
                        $media_id = $whatsapp->uploadMediaFile($attachment_path);
                    } catch (\Exception $e) {
                        log_message('error', 'Error uploading attachment: ' . $e->getMessage());
                    }
                }

                if (empty($media_id) && $marketing_template === 'taz_market') {
                    $candidate_images = [
                        FCPATH . 'uploads/graff.png',
                        FCPATH . 'images/logo.png',
                        FCPATH . 'images/logowithouttext256.png',
                        FCPATH . 'images/enp.PNG'
                    ];
                    foreach ($candidate_images as $imgPath) {
                        if (file_exists($imgPath)) {
                            try {
                                $media_id = $whatsapp->uploadMediaFile($imgPath);
                                $header_type = 'image';
                                $filename = basename($imgPath);
                                break;
                            } catch (\Exception $e) {
                                log_message('error', 'Error uploading fallback image: ' . $e->getMessage());
                            }
                        }
                    }
                }

                $body_param = !empty($message) ? $message : 'Valued Customer';

                if (!empty($media_id)) {
                    $response = $whatsapp->sendTemplateWithMediaId(
                        $phone,
                        $marketing_template,
                        $media_id,
                        $filename,
                        [$body_param],
                        $header_type
                    );
                } else {
                    $response = $whatsapp->sendTemplate(
                        $phone,
                        $marketing_template,
                        null,
                        null,
                        null,
                        [$body_param]
                    );
                }
            } else {
                $response = $whatsapp->sendMessage($phone, $message ?: 'Hello from our store!');
            }
        } elseif ($message_type === 'email') {
            $email_lib = new \App\Libraries\Email_lib();
            $response = $email_lib->sendEmail($phone, $subject, $message, $attachment_path);
        } else {
            $response = $this->sms_lib->sendSMS($phone, $message);
        }

        if ($response) {
            return $this->response->setJSON([
                'success'   => true,
                'message'   => lang('Messages.successfully_sent') . ' ' . esc($phone),
                'person_id' => $person_id
            ]);
        } else {
            $lastErr = isset($whatsapp) ? $whatsapp->getLastError() : null;
            $detail = $lastErr ? " Meta Error: {$lastErr}" : '';
            return $this->response->setJSON([
                'success'   => false,
                'message'   => lang('Messages.unsuccessfully_sent') . ' ' . esc($phone) . '.' . $detail,
                'person_id' => NEW_ENTRY
            ]);
        }
    }

    public function send(): ResponseInterface
    {
        return $this->postSend();
    }

    public function send_form(int $person_id = NEW_ENTRY): ResponseInterface
    {
        return $this->postSendForm($person_id);
    }
}