<?php

namespace App\Libraries;

use App\Models\Appconfig;

class WhatsappLib
{
    private array $config;
    private ?string $lastError = null;

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

    public function getLastError(): ?string
    {
        return $this->lastError;
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
                $this->lastError = 'Meta WhatsApp access token or phone number ID is missing.';
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
            $this->lastError = 'Twilio WhatsApp configuration is missing.';
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
            $this->lastError = null;
            return true;
        } else {
            $this->lastError = "Twilio Error (HTTP {$http_code}): {$response}";
            log_message('error', 'Twilio WhatsApp API Error: ' . $response);
            return false;
        }
    }

    /**
     * Send WhatsApp Message via Meta Template Engine
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
            $this->lastError = 'Meta WhatsApp configuration is missing.';
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

        // Fallback for taz_market if body params empty
        if ($templateName === 'taz_market' && empty($bodyParams)) {
            $bodyParams = ['Valued Customer'];
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
     * Upload binary content (image/pdf/video/audio) to Meta media endpoint and return media ID.
     */
    public function uploadMediaBinary(string $content, string $filename, string $mimeType = 'application/pdf'): string
    {
        $access_token    = trim($this->config['meta_access_token'] ?? '');
        $phone_number_id = trim($this->config['meta_phone_number_id'] ?? '');
        if (empty($access_token) || empty($phone_number_id)) {
            $this->lastError = 'Meta WhatsApp credentials missing for media upload.';
            throw new \RuntimeException('Meta WhatsApp config missing for media upload.');
        }

        $url      = "https://graph.facebook.com/v19.0/{$phone_number_id}/media";
        $boundary = '----FormBoundary' . bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"messaging_product\"\r\n\r\nwhatsapp\r\n"
            . "--{$boundary}\r\nContent-Disposition: form-data; name=\"type\"\r\n\r\n{$mimeType}\r\n"
            . "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\nContent-Type: {$mimeType}\r\n\r\n"
            . $content . "\r\n--{$boundary}--\r\n";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$access_token}",
                "Content-Type: multipart/form-data; boundary={$boundary}"
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($responseBody, true);
        if ($httpCode !== 200 || empty($decoded['id'])) {
            $errMsg = $decoded['error']['message'] ?? $responseBody;
            $this->lastError = "Media upload failed: {$errMsg}";
            throw new \RuntimeException("WhatsApp media upload failed (HTTP {$httpCode}): {$errMsg}");
        }

        log_message('info', "WhatsApp: media uploaded successfully, ID={$decoded['id']}");
        return (string) $decoded['id'];
    }

    /**
     * Upload a PDF binary to Meta media endpoint and return the media ID.
     */
    public function uploadMedia(string $pdfContent, string $filename): string
    {
        return $this->uploadMediaBinary($pdfContent, $filename, 'application/pdf');
    }

    /**
     * Upload a local file (e.g. image/poster/brochure) to Meta media endpoint and return media ID.
     */
    public function uploadMediaFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Media file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $filename = basename($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeMap = [
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'mp4'  => 'video/mp4'
        ];
        $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

        return $this->uploadMediaBinary($content, $filename, $mimeType);
    }

    /**
     * Send a Meta template using an already-uploaded media ID.
     * Supports document (PDF invoice) and image (marketing poster).
     */
    public function sendTemplateWithMediaId(
        string $phone,
        string $templateName,
        string $mediaId,
        string $filename = '',
        array $bodyParams = [],
        string $headerType = 'document'
    ): bool {
        $access_token    = trim($this->config['meta_access_token'] ?? '');
        $phone_number_id = trim($this->config['meta_phone_number_id'] ?? '');
        $language_code   = $this->config['whatsapp_template_language'] ?? 'en';

        if (empty($access_token) || empty($phone_number_id)) {
            $this->lastError = 'Meta WhatsApp credentials missing for sendTemplateWithMediaId.';
            log_message('error', 'Meta WhatsApp config missing for sendTemplateWithMediaId.');
            return false;
        }

        $phone = ltrim($this->formatPhone($phone), '+');
        $url   = "https://graph.facebook.com/v19.0/{$phone_number_id}/messages";

        if ($headerType === 'image') {
            $headerParam = [
                'type'  => 'image',
                'image' => ['id' => $mediaId]
            ];
        } elseif ($headerType === 'video') {
            $headerParam = [
                'type'  => 'video',
                'video' => ['id' => $mediaId]
            ];
        } else {
            $docParam = ['id' => $mediaId];
            if (!empty($filename)) {
                $docParam['filename'] = $filename;
            }
            $headerParam = [
                'type'     => 'document',
                'document' => $docParam
            ];
        }

        $components = [
            [
                'type'       => 'header',
                'parameters' => [$headerParam]
            ]
        ];

        // Ensure body param is present for templates requiring it (e.g. taz_market)
        if ($templateName === 'taz_market' && empty($bodyParams)) {
            $bodyParams = ['Valued Customer'];
        }

        if (!empty($bodyParams)) {
            $textParams = array_map(fn($p) => ['type' => 'text', 'text' => (string)$p], $bodyParams);
            $components[] = ['type' => 'body', 'parameters' => $textParams];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => $language_code],
                'components' => $components
            ]
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
            $this->lastError = null;
            log_message('info', 'Meta WhatsApp Sent Successfully: ' . $response);
            return true;
        } else {
            $errData = json_decode($response, true);
            $msg = $errData['error']['message'] ?? "HTTP {$http_code}";
            if (!empty($errData['error']['error_data']['details'])) {
                $msg .= ' (' . $errData['error']['error_data']['details'] . ')';
            }
            $this->lastError = $msg;
            log_message('error', 'Meta WhatsApp API Error (HTTP ' . $http_code . '): ' . $response);
            return false;
        }
    }
}