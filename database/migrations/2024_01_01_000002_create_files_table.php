<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('original_name');
            $table->string('mime_type');
            $table->bigInteger('size');
            $table->string('path');
            $table->string('folder')->default('/');
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->string('encryption_password')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'folder']);
            $table->index(['user_id', 'is_hidden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
