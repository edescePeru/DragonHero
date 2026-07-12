<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ConvertUsersTableToInnoDB extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE users ENGINE = InnoDB');
    }

    public function down()
    {
        // Intentionally permanent. Returning users to MyISAM would disable
        // transactions and break current or future InnoDB foreign keys.
    }
}
