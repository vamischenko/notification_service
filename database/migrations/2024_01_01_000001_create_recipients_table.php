<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipients', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20)->unique()->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE INDEX idx_recipients_email ON recipients(email) WHERE email IS NOT NULL');
        DB::statement('CREATE INDEX idx_recipients_phone ON recipients(phone) WHERE phone IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('recipients');
    }
};
