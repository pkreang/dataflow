<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('token', 96);
            $table->foreignId('document_form_id')->constrained('document_forms')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_received_at')->nullable();
            $table->unsignedInteger('received_count')->default(0);
            $table->json('last_payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_webhooks');
    }
};
