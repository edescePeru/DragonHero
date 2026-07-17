<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
class AddCharacterStatsSnapshotToHuntsTable extends Migration{public function up(){Schema::table('hunts',function(Blueprint $t){$t->json('character_stats_snapshot')->nullable()->after('character_health_after');});}public function down(){Schema::table('hunts',function(Blueprint $t){$t->dropColumn('character_stats_snapshot');});}}
