<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_sessions')) {
            Schema::create('whatsapp_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 64)->unique();
                $table->string('status', 32)->default('disconnected');
                $table->string('phone_number', 32)->nullable();
                $table->string('profile_name', 255)->nullable();
                $table->longText('qr_code')->nullable();
                $table->longText('creds')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('disconnected_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('whatsapp_auth_keys')) {
            Schema::create('whatsapp_auth_keys', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 64)->index();
                $table->string('category', 64);
                $table->string('key_id', 191);
                $table->longText('value');
                $table->timestamps();

                $table->unique(['tenant_id', 'category', 'key_id'], 'unique_tenant_cat_key');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_auth_keys');
        Schema::dropIfExists('whatsapp_sessions');
    }
};
