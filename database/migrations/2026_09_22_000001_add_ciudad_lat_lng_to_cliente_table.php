<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            if (!Schema::hasColumn('cliente', 'ciudad')) {
                $table->string('ciudad', 100)->nullable()->after('direccion');
            }
            if (!Schema::hasColumn('cliente', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('ciudad');
            }
            if (!Schema::hasColumn('cliente', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropColumn(['ciudad', 'lat', 'lng']);
        });
    }
};
