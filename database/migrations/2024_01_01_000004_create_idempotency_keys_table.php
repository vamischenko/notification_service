<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->jsonb('response');
            $table->smallInteger('status_code');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');
        });

        DB::statement('CREATE INDEX idx_idempotency_expires_at ON idempotency_keys(expires_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
