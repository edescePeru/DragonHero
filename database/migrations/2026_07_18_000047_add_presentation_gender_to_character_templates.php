<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPresentationGenderToCharacterTemplates extends Migration
{
    public function up()
    {
        Schema::table('character_templates', function (Blueprint $table) {
            $table->string('presentation_gender', 16)->nullable()->after('character_class_id');
            $table->index(['status', 'presentation_gender'], 'templates_status_gender_index');
        });
    }

    public function down()
    {
        Schema::table('character_templates', function (Blueprint $table) {
            $table->dropIndex('templates_status_gender_index');
            $table->dropColumn('presentation_gender');
        });
    }
}
