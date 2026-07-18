<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StrengthenRefinementOperationIdempotency extends Migration
{
    public function up()
    {
        $collision = DB::table('item_instance_events')
            ->select('item_instance_id', 'operation_uuid', DB::raw('COUNT(*) AS result_count'))
            ->whereNotNull('operation_uuid')
            ->groupBy('item_instance_id', 'operation_uuid')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($collision) {
            throw new RuntimeException('Cannot strengthen refinement idempotency: operation_uuid collision exists for an ItemInstance.');
        }

        Schema::table('item_instance_events', function (Blueprint $table) {
            $table->dropUnique('instance_event_operation_unique');
            $table->unique(['item_instance_id', 'operation_uuid'], 'instance_operation_unique');
        });
    }

    public function down()
    {
        Schema::table('item_instance_events', function (Blueprint $table) {
            $table->dropUnique('instance_operation_unique');
            $table->unique(['item_instance_id', 'event_type', 'operation_uuid'], 'instance_event_operation_unique');
        });
    }
}
