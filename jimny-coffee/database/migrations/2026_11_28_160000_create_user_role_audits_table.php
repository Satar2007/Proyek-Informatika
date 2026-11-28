<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_audits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('actor_name');
          $table->string('actor_email');
            $table->string('actor_role', 32)->nullable();

            $table->foreignId('target_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('target_name');
            $table->string('target_email');

            $table->string('event', 32);
            $table->string('old_role', 32)->nullable();
            $table->string('new_role', 32)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(
                ['target_user_id', 'created_at'],
                'ura_target_created_idx'
            );

            $table->index(
                ['actor_user_id', 'created_at'],
                'ura_actor_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_audits');
    }
};
