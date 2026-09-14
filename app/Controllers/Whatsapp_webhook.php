<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class Whatsapp_webhook extends Controller
{
    /**
     * Handle Meta WhatsApp Webhook GET (Verification) & POST (Notifications)
     */
    public function index()
    {
        $request = service('request');
        $method = strtolower($request->getMethod());

        // 1. Meta Webhook Verification Challenge (GET Request)
        if ($method === 'get') {
            $mode      = $request->getGet('hub_mode') ?? $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? null;
            $token     = $request->getGet('hub_verify_token') ?? $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? null;
            $challenge = $request->getGet('hub_challenge') ?? $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? null;

            if ($mode === 'subscribe' || !empty($token)) {
                log_message('info', 'WhatsApp Webhook Verified Successfully.');
                http_response_code(200);
                echo $challenge;
                exit(0);
            }

            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        // 2. Incoming Webhook Event / Delivery Status (POST Request)
        if ($method === 'post') {
            $json = $request->getJSON(true);
            log_message('info', 'WhatsApp Webhook Received: ' . json_encode($json));

            http_response_code(200);
            echo json_encode(['status' => 'success']);
            exit(0);
        }

        return $this->response->setStatusCode(405)->setBody('Method Not Allowed');
    }
}
