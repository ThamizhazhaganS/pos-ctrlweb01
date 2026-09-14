<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Migration_AddWhatsappConfigKeys extends Migration
{
    public function up(): void
    {
        $whatsappValues = [
            ['key' => 'whatsapp_enable', 'value' => '0'],
            ['key' => 'whatsapp_api_url', 'value' => ''],
            ['key' => 'whatsapp_api_token', 'value' => ''],
            ['key' => 'whatsapp_sender_number', 'value' => ''],
            ['key' => 'whatsapp_receipt_message', 'value' => 'Thank you for your purchase!'],
        ];

        $this->db->table('app_config')->ignore(true)->insertBatch($whatsappValues);
    }

    public function down(): void
    {
        $whatsappKeys = [
            'whatsapp_enable',
            'whatsapp_api_url',
            'whatsapp_api_token',
            'whatsapp_sender_number',
            'whatsapp_receipt_message',
        ];

        $this->db->table('app_config')
            ->whereIn('key', $whatsappKeys)
            ->delete();
    }
}
