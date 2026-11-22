<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Antes, elegir "En mora" guardaba 'Vencido'. Ahora 'Vencido' y 'En mora' son
    // estados distintos, así que esos registros pasan a 'En mora' para conservar
    // lo que el usuario había elegido.
    public function up(): void
    {
        DB::table('Alquiler')->whereRaw('LOWER(estado) = ?', ['vencido'])->update(['estado' => 'En mora']);
    }

    public function down(): void
    {
        DB::table('Alquiler')->whereRaw('LOWER(estado) IN (?, ?, ?)', ['en mora', 'en curso', 'vencido'])
            ->update(['estado' => DB::raw("CASE WHEN LOWER(estado) = 'en curso' THEN 'Activo' ELSE 'Vencido' END")]);
    }
};
