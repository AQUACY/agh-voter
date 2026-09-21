<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('elections', 'unopposed_voting_enabled')) {
            Schema::table('elections', function (Blueprint $table) {
                $table->boolean('unopposed_voting_enabled')->default(false)->after('published_at');
                $table->string('unopposed_voting_scope', 16)->nullable()->after('unopposed_voting_enabled');
                $table->string('unopposed_threshold_type', 16)->nullable()->after('unopposed_voting_scope');
                $table->unsignedInteger('unopposed_threshold_value')->nullable()->after('unopposed_threshold_type');
                $table->string('unopposed_fail_outcome', 32)->nullable()->after('unopposed_threshold_value');
                $table->text('unopposed_fail_note')->nullable()->after('unopposed_fail_outcome');
            });
        }

        if (! Schema::hasColumn('positions', 'unopposed_yes_no')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->boolean('unopposed_yes_no')->default(false)->after('max_selections');
                $table->string('unopposed_threshold_type', 16)->nullable()->after('unopposed_yes_no');
                $table->unsignedInteger('unopposed_threshold_value')->nullable()->after('unopposed_threshold_type');
                $table->string('unopposed_fail_outcome', 32)->nullable()->after('unopposed_threshold_value');
                $table->text('unopposed_fail_note')->nullable()->after('unopposed_fail_outcome');
            });
        }

        if (! Schema::hasColumn('votes', 'choice')) {
            Schema::table('votes', function (Blueprint $table) {
                $table->string('choice', 8)->nullable()->after('candidate_id');
                $table->index(['election_id', 'position_id', 'choice']);
            });
        }

        if (! Schema::hasColumn('manual_tallies', 'choice')) {
            Schema::table('manual_tallies', function (Blueprint $table) {
                $table->string('choice', 8)->default('yes')->after('candidate_id');
            });
        }

        DB::table('manual_tallies')->whereNull('choice')->update(['choice' => 'yes']);

        // MySQL will not drop the old unique index while FKs rely on it.
        // Drop FKs → replace unique → restore FKs (and a plain election_id index).
        if ($this->hasIndex('manual_tallies', 'manual_tallies_election_id_candidate_id_unique')) {
            Schema::table('manual_tallies', function (Blueprint $table) {
                $table->dropForeign(['election_id']);
                $table->dropForeign(['candidate_id']);
            });

            Schema::table('manual_tallies', function (Blueprint $table) {
                $table->dropUnique(['election_id', 'candidate_id']);
            });

            Schema::table('manual_tallies', function (Blueprint $table) {
                $table->unique(['election_id', 'candidate_id', 'choice']);
                $table->foreign('election_id')->references('id')->on('elections')->cascadeOnDelete();
                $table->foreign('candidate_id')->references('id')->on('candidates')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('manual_tallies', 'manual_tallies_election_id_candidate_id_choice_unique')) {
            Schema::table('manual_tallies', function (Blueprint $table) {
                $table->dropForeign(['election_id']);
                $table->dropForeign(['candidate_id']);
            });

            Schema::table('manual_tallies', function (Blueprint $table) {
                $table->dropUnique(['election_id', 'candidate_id', 'choice']);
            });

            Schema::table('manual_tallies', function (Blueprint $table) {
                $table->unique(['election_id', 'candidate_id']);
                $table->foreign('election_id')->references('id')->on('elections')->cascadeOnDelete();
                $table->foreign('candidate_id')->references('id')->on('candidates')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('manual_tallies', 'choice')) {
            Schema::table('manual_tallies', function (Blueprint $table) {
                $table->dropColumn('choice');
            });
        }

        if (Schema::hasColumn('votes', 'choice')) {
            Schema::table('votes', function (Blueprint $table) {
                $table->dropIndex(['election_id', 'position_id', 'choice']);
                $table->dropColumn('choice');
            });
        }

        if (Schema::hasColumn('positions', 'unopposed_yes_no')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->dropColumn([
                    'unopposed_yes_no',
                    'unopposed_threshold_type',
                    'unopposed_threshold_value',
                    'unopposed_fail_outcome',
                    'unopposed_fail_note',
                ]);
            });
        }

        if (Schema::hasColumn('elections', 'unopposed_voting_enabled')) {
            Schema::table('elections', function (Blueprint $table) {
                $table->dropColumn([
                    'unopposed_voting_enabled',
                    'unopposed_voting_scope',
                    'unopposed_threshold_type',
                    'unopposed_threshold_value',
                    'unopposed_fail_outcome',
                    'unopposed_fail_note',
                ]);
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("pragma index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
