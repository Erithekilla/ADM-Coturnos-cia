<?php

/**
 * LIMPEZA PÓS-CONVERSÃO — IRREVERSÍVEL.
 *
 * Remove as tabelas legacy_* deixadas pela conversão. Rode só depois de conferir
 * o sistema funcionando sobre o schema novo E de ter um backup (mysqldump) guardado:
 *   php artisan migrate --path=database/migrations/conversao/2026_11_04_000002_remover_tabelas_legadas.php
 *
 * Depois disso, migrate:rollback da conversão deixa de ser possível.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('legacy_conversion_map')) {
            throw new RuntimeException('Nenhuma conversão encontrada (tabela legacy_conversion_map ausente). Nada a remover.');
        }

        $tables = DB::table('legacy_conversion_map')->pluck('legacy')->all();
        $tables[] = 'legacy_migrations';
        $tables[] = 'legacy_conversion_fks';

        Schema::disableForeignKeyConstraints();
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::drop('legacy_conversion_map');

        // a conversão não pode mais ser desfeita: tira o registro dela do histórico
        DB::table('migrations')->where('migration', '2026_11_04_000001_converter_banco_legado')->delete();

        echo '  [limpeza] ' . count($tables) . ' tabela(s) legacy_* removida(s).' . PHP_EOL;
    }

    public function down(): void
    {
        // Irreversível: os dados antigos só voltam pelo backup.
    }
};
