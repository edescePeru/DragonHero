<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddInventoryCapacityToCharactersAndItems extends Migration
{
    public function up()
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('base_inventory_slots')->default(30)->after('status');
        });

        DB::table('characters')->whereNull('base_inventory_slots')->update(['base_inventory_slots' => 30]);
        DB::table('items')->whereNull('max_stack')->update(['max_stack' => 1]);
        DB::statement('ALTER TABLE items MODIFY max_stack INT UNSIGNED NOT NULL DEFAULT 1');
    }

    public function down()
    {
        DB::statement('ALTER TABLE items MODIFY max_stack INT UNSIGNED NULL DEFAULT NULL');
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('base_inventory_slots');
        });
    }
}
