<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('path');
            $table->string('parent_path')->nullable()->default('/');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'path']);
            $table->index(['user_id', 'parent_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_folders');
    }
};
