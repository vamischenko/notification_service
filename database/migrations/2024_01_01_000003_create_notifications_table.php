<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('batch_id')->constrained('notification_batches')->cascadeOnDelete();
            $table->foreignUuid('recipient_id')->constrained('recipients');
            $table->string('channel', 10);
            $table->string('priority', 20);
            $table->text('message_text');
            $table->string('status', 20)->default('queued');
            $table->string('idempotency_key', 64)->unique();
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->smallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notif_status CHECK (status IN ('queued','sent','delivered','discarded'))");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notif_channel CHECK (channel IN ('sms', 'email'))");

        DB::statement('CREATE INDEX idx_notifications_recipient_created ON notifications(recipient_id, created_at DESC)');
        DB::statement('CREATE INDEX idx_notifications_batch_id ON notifications(batch_id)');
        DB::statement("CREATE INDEX idx_notifications_status_active ON notifications(status) WHERE status IN ('queued', 'sent')");
        DB::statement('CREATE INDEX idx_notifications_recipient_status ON notifications(recipient_id, status, created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
