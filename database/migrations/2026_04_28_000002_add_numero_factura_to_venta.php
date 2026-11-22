<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('Venta', function (Blueprint $table) {
            $table->string('numero_factura', 20)->nullable()->after('id_venta');
        });
    }

    public function down(): void
    {
        Schema::table('Venta', function (Blueprint $table) {
            $table->dropColumn('numero_factura');
        });
    }
};
