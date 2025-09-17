<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            //
            if (!Schema::hasColumn('activity_logs', 'subject_type')) {
                $table->string('subject_type')->nullable();
            }
            if (!Schema::hasColumn('activity_logs', 'subject_id')) {
                $table->unsignedBigInteger('subject_id')->nullable();
            }
            if (!Schema::hasColumn('activity_logs', 'action')) {
                $table->string('action')->nullable();
            }
        });
         // add indexes only if they don't exist (works on MySQL & MariaDB)
        if (!$this->indexExists('activity_logs', 'activity_logs_subject_type_index')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->index('subject_type', 'activity_logs_subject_type_index');
            });
        }
        if (!$this->indexExists('activity_logs', 'activity_logs_subject_id_index')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->index('subject_id', 'activity_logs_subject_id_index');
            });
        }

        if (!$this->indexExists('activity_logs', 'activity_logs_action_index')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->index('action', 'activity_logs_action_index');
            });
        }

        if (Schema::hasColumn('activity_logs', 'created_at') &&
            !$this->indexExists('activity_logs', 'activity_logs_created_at_index')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->index('created_at', 'activity_logs_created_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop indexes if they exist (MySQL syntax requires the table name)
        if ($this->indexExists('activity_logs', 'activity_logs_subject_type_index')) {
            DB::statement('DROP INDEX activity_logs_subject_type_index ON activity_logs');
        }
        if ($this->indexExists('activity_logs', 'activity_logs_subject_id_index')) {
            DB::statement('DROP INDEX activity_logs_subject_id_index ON activity_logs');
        }
        if ($this->indexExists('activity_logs', 'activity_logs_action_index')) {
            DB::statement('DROP INDEX activity_logs_action_index ON activity_logs');
        }
        if ($this->indexExists('activity_logs', 'activity_logs_created_at_index')) {
            DB::statement('DROP INDEX activity_logs_created_at_index ON activity_logs');
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'subject_id')) {
                $table->dropColumn('subject_id');
            }
            if (Schema::hasColumn('activity_logs', 'subject_type')) {
                $table->dropColumn('subject_type');
            }
        });
    }
    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::connection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
