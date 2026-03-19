<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tg_bots', function (Blueprint $table) {
            $table->string('secret_token', 256)->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('tg_bots', function (Blueprint $table) {
            $table->dropColumn('secret_token');
        });
    }
};
