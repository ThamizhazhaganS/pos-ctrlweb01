<?php

namespace App\Libraries;

use App\Models\Appconfig;

class WhatsappLib
{
    private array $config;

    public function __construct()
    {
        $appconfig = new Appconfig();
        $this->config = $appconfig->get_all()->getResultArray();
        $config_assoc = [];
        foreach ($this->config as $row) {
            $config_assoc[$row['key']] = $row['value'];
        }
        $this->config = $config_assoc;
    }

    /**
     * Clean and format phone number for WhatsApp (+91 or country code prefix)
     */
    public function formatPhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        $default_country_code = $this->config['whatsapp_default_country_code'] ?? '91';

        if (strlen($clean) === 10) {
            $clean = $default_country_code . $clean;
        }

        return '+' . $clean;
    }

    /**
     * Send free-form WhatsApp message (Twilio or Meta API)
     */
    public function sendMessage(string $phone, string $message): bool
    {
        $provider = $this->config['whatsapp_api_provider'] ?? 'twilio';
        $phone = $this->formatPhone($phone);

        if ($provider === 'meta') {
            $access_token = trim($this->config['meta_access_token'] ?? '');
            $phone_number_id = trim($this->config['meta_phone_number_id'] ?? '');
            
            if (empty($access_token) || empty($phone_number_id)) {
                log_message('error', 'Meta WhatsApp configuration is missing.');
                return false;
            }

            $url = "https://graph.facebook.com/v18.0/{$phone_number_id}/messages";
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => ltrim($phone, '+'),
                'type'              => 'text',
                'text'              => ['body' => $message]
            ];

            return $this->postToMeta($url, $access_token, $payload);
        }

        // Default Twilio sending
        $account_sid = $this->config['twilio_account_sid'] ?? '';
        $auth_token = $this->config['twilio_auth_token'] ?? '';
        $from_number = $this->config['twilio_whatsapp_number'] ?? '';

        if (empty($account_sid) || empty($auth_token) || empty($from_number)) {
            log_message('error', 'Twilio WhatsApp configuration is missing.');
            return false;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";
        $data = [
            'From' => 'whatsapp:' . $from_number,
            'To'   => 'whatsapp:' . $phone,
            'Body' => $message
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$account_sid}:{$auth_token}");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            return true;
        } else {
            log_message('error', 'Twilio WhatsApp API Error: ' . $response);
            return false;
        }
    }

    /**
     * Send WhatsApp Message via Meta Template Engine
     *
     * @param string $phone Customer Phone number
     * @param string $templateName Name of approved Meta Template
     * @param string|null $headerType 'document', 'image', 'video', or null
     * @param string|null $mediaUrl URL of header media
     * @param string|null $filename Filename for document header
     * @param array|string|null $bodyParams Dynamic variable replacement array [{{1}}, {{2}}, {{3}}, {{4}}]
     * @param string|null $buttonParam Dynamic parameter for URL button
     * @return bool
     */
    public function sendTemplate(
        string $phone,
        string $templateName,
        ?string $headerType = null,
        ?string $mediaUrl = null,
        ?string $filename = null,
        $bodyParams = null,
        ?string $buttonParam = null
    ): bool {
        $access_token = trim($this->config['meta_access_token'] ?? '');
        $phone_number_id = trim($this->config['meta_phone_number_id'] ?? '');
        $language_code = $this->config['whatsapp_template_language'] ?? 'en';

        if (empty($access_token) || empty($phone_number_id)) {
            log_message('error', 'Meta WhatsApp configuration is missing.');
            return false;
        }

        $phone = ltrim($this->formatPhone($phone), '+');
        $url = "https://graph.facebook.com/v18.0/{$phone_number_id}/messages";

        $components = [];

        // 1. Build Header Component if media URL is provided and valid public URL (skip for hello_world or localhost)
        if (!empty($headerType) && !empty($mediaUrl) && $templateName !== 'hello_world') {
            if (!str_contains($mediaUrl, 'localhost') && !str_contains($mediaUrl, '127.0.0.1')) {
                $header_param = [
                    'type' => $headerType,
                    $headerType => [
                        'link' => $mediaUrl
                    ]
                ];

                if ($headerType === 'document' && !empty($filename)) {
                    $header_param['document']['filename'] = $filename;
                }

                $components[] = [
                    'type'       => 'header',
                    'parameters' => [$header_param]
                ];
            }
        }

        // 2. Build Body Component for template variables (e.g. {{1}}, {{2}}, {{3}}, {{4}})
        if (!empty($bodyParams) && $templateName !== 'hello_world') {
            $params = [];
            if (is_array($bodyParams)) {
                foreach ($bodyParams as $param) {
                    $params[] = [
                        'type' => 'text',
                        'text' => (string)$param
                    ];
                }
            } else {
                $params[] = [
                    'type' => 'text',
                    'text' => (string)$bodyParams
                ];
            }

            if (!empty($params)) {
                $components[] = [
                    'type'       => 'body',
                    'parameters' => $params
                ];
            }
        }

        // 3. Build Button Component for dynamic URL button
        if (!empty($buttonParam) && $templateName !== 'hello_world') {
            $components[] = [
                'type'       => 'button',
                'sub_type'   => 'url',
                'index'      => '0',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $buttonParam
                    ]
                ]
            ];
        }

        $template_data = [
            'name'     => $templateName,
            'language' => ['code' => $language_code]
        ];

        if (!empty($components)) {
            $template_data['components'] = $components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => $template_data
        ];

        return $this->postToMeta($url, $access_token, $payload);
    }

    /**
     * Upload a PDF binary to Meta media endpoint and return the media ID.
     */
    public function uploadMedia(string $pdfContent, string $filename): string
    {
        $access_token    = trim($this->config['meta_access_token'] ?? '');
        $phone_number_id = trim($this->config['meta_phone_number_id'] ?? '');
        if (empty($access_token) || empty($phone_number_id)) {
            throw new \RuntimeException('Meta WhatsApp config missing for media upload.');
        }
        $url      = "https://graph.facebook.com/v19.0/{$phone_number_id}/media";
        $boundary = '----FormBoundary' . bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"messaging_product\"\r\n\r\nwhatsapp\r\n"
            . "--{$boundary}\r\nContent-Disposition: form-data; name=\"type\"\r\n\r\napplication/pdf\r\n"
            . "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\nContent-Type: application/pdf\r\n\r\n"
            . $pdfContent . "\r\n--{$boundary}--\r\n";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$access_token}", "Content-Type: multipart/form-data; boundary={$boundary}"],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode($responseBody, true);
        if ($httpCode !== 200 || empty($decoded['id'])) {
            throw new \RuntimeException("WhatsApp media upload failed (HTTP {$httpCode}): " . ($decoded['error']['message'] ?? $responseBody));
        }
        log_message('info', "WhatsApp: media uploaded, ID={$decoded['id']}");
        return (string) $decoded['id'];
    }

    /**
     * Send a Meta template using an already-uploaded media ID.
     */
    public function sendTemplateWithMediaId(string $phone, string $templateName, string $mediaId, string $filename, array $bodyParams = []): bool
    {
        $access_token    = trim($this->config['meta_access_token'] ?? '');
        $phone_number_id = trim($this->config['meta_phone_number_id'] ?? '');
        $language_code   = $this->config['whatsapp_template_language'] ?? 'en_US';
        if (empty($access_token) || empty($phone_number_id)) {
            log_message('error', 'Meta WhatsApp config missing for sendTemplateWithMediaId.');
            return false;
        }
        $phone = ltrim($this->formatPhone($phone), '+')
;
        $url   = "https://graph.facebook.com/v19.0/{$phone_number_id}/messages";
        $components = [[
            'type'       => 'header',
            'parameters' => [['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => $filename]]],
        ]];
        if (!empty($bodyParams)) {
            $textParams = array_map(fn($p) => ['type' => 'text', 'text' => (string)$p], $bodyParams);
            $components[] = ['type' => 'body', 'parameters' => $textParams];
        }
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => ['name' => $templateName, 'language' => ['code' => $language_code], 'components' => $components],
        ];
        return $this->postToMeta($url, $access_token, $payload);
    }

    /**
     * Helper to execute cURL POST request to Meta Graph API
     */
    private function postToMeta(string $url, string $accessToken, array $payload): bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            log_message('info', 'Meta WhatsApp Sent Successfully: ' . $response);
            return true;
        } else {
            log_message('error', 'Meta WhatsApp API Error (HTTP ' . $http_code . '): ' . $response);
            return false;
        }
    }
}
