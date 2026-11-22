<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('herramienta', function (Blueprint $table) {
            $table->string('estado', 20)->default('activo')->after('id_proveedor');
        });
        Schema::table('proveedor', function (Blueprint $table) {
            $table->string('estado', 20)->default('activo')->after('contacto');
        });
    }

    public function down(): void
    {
        Schema::table('herramienta', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
        Schema::table('proveedor', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
