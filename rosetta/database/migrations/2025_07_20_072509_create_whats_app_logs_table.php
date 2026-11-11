<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsAppLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('whats_app_logs', function (Blueprint $table) {
            $table->id();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->integer('status_code')->nullable();
            $table->timestamps();
            $table->softDeletes(); // adds deleted_at column for soft deletes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whats_app_logs');
    }
}
