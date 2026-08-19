<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('share_token', 64)->unique();
            $table->string('password')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('download_limit')->nullable();
            $table->integer('download_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('share_token');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_shares');
    }
};
