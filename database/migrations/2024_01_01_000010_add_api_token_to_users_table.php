<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 64)->nullable()->unique()->after('avatar');
        });

        // Generate API tokens for existing users
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['api_token' => \Illuminate\Support\Str::random(64)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('api_token');
        });
    }
};
