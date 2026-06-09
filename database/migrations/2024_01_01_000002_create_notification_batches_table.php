<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_batches', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('channel', 10);
            $table->string('priority', 20);
            $table->text('message_text');
            $table->integer('total_count')->default(0);
            $table->integer('queued_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('discarded_count')->default(0);
            $table->string('idempotency_key', 64)->unique()->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE notification_batches ADD CONSTRAINT chk_batch_channel CHECK (channel IN ('sms', 'email'))");
        DB::statement("ALTER TABLE notification_batches ADD CONSTRAINT chk_batch_priority CHECK (priority IN ('transactional', 'marketing'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_batches');
    }
};
