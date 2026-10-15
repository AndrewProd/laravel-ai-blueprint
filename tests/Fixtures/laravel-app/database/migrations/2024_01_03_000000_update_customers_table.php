<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->renameColumn('name', 'full_name');
        });

        Schema::create(config('audit.table'), function (Blueprint $table) {
            $table->id();
        });
    }
};
