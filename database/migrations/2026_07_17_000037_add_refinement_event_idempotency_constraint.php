<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRefinementEventIdempotencyConstraint extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('item_instance_events', 'operation_uuid')) {
            Schema::table('item_instance_events', function (Blueprint $table) {
                $table->char('operation_uuid', 36)->nullable()->collation('ascii_bin')->after('item_instance_id');
                $table->index('operation_uuid', 'instance_events_operation');
            });
        } else {
            DB::statement('ALTER TABLE item_instance_events MODIFY operation_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL');
        }

        $duplicate = DB::table('item_instance_events')
            ->select('item_instance_id', 'event_type', 'operation_uuid', DB::raw('COUNT(*) AS duplicate_count'))
            ->whereNotNull('operation_uuid')
            ->groupBy('item_instance_id', 'event_type', 'operation_uuid')
            ->havingRaw('COUNT(*) > 1')
            ->first();
        if ($duplicate) {
            throw new \RuntimeException('Cannot add refinement idempotency constraint: duplicate item_instance_events exist.');
        }

        Schema::table('item_instance_events', function (Blueprint $table) {
            $table->unique(['item_instance_id', 'event_type', 'operation_uuid'], 'instance_event_operation_unique');
        });
    }

    public function down()
    {
        Schema::table('item_instance_events', function (Blueprint $table) {
            $table->dropUnique('instance_event_operation_unique');
        });
    }
}
