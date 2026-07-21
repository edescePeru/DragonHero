<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddActiveCharacterIdToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('active_character_id')->nullable()->after('id');
            $table->index('active_character_id', 'users_active_character_index');
        });

        DB::table('users')->select('id')->orderBy('id')->chunk(200, function ($users) {
            foreach ($users as $user) {
                $ids = DB::table('characters')->where('user_id', $user->id)->limit(2)->pluck('id');
                if ($ids->count() === 1) {
                    DB::table('users')->where('id', $user->id)->update(['active_character_id' => $ids->first()]);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('active_character_id', 'users_active_character_foreign')->references('id')->on('characters')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_active_character_foreign');
            $table->dropIndex('users_active_character_index');
            $table->dropColumn('active_character_id');
        });
    }
}
