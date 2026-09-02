<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_logs')) {
            Schema::create('whatsapp_logs', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 64)->default('default')->index();
                $table->string('direction', 20)->default('outbound')->index(); // outbound, inbound, system
                $table->string('phone', 64)->nullable()->index();
                $table->string('status', 32)->default('sent')->index(); // sent, received, failed, error, connected, disconnected
                $table->string('message_id', 191)->nullable();
                $table->longText('message_body')->nullable();
                $table->longText('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
