<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->boolean('unopposed_voting_enabled')->default(false)->after('published_at');
            $table->string('unopposed_voting_scope', 16)->nullable()->after('unopposed_voting_enabled');
            $table->string('unopposed_threshold_type', 16)->nullable()->after('unopposed_voting_scope');
            $table->unsignedInteger('unopposed_threshold_value')->nullable()->after('unopposed_threshold_type');
            $table->string('unopposed_fail_outcome', 32)->nullable()->after('unopposed_threshold_value');
            $table->text('unopposed_fail_note')->nullable()->after('unopposed_fail_outcome');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->boolean('unopposed_yes_no')->default(false)->after('max_selections');
            $table->string('unopposed_threshold_type', 16)->nullable()->after('unopposed_yes_no');
            $table->unsignedInteger('unopposed_threshold_value')->nullable()->after('unopposed_threshold_type');
            $table->string('unopposed_fail_outcome', 32)->nullable()->after('unopposed_threshold_value');
            $table->text('unopposed_fail_note')->nullable()->after('unopposed_fail_outcome');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->string('choice', 8)->nullable()->after('candidate_id');
            $table->index(['election_id', 'position_id', 'choice']);
        });

        Schema::table('manual_tallies', function (Blueprint $table) {
            $table->string('choice', 8)->default('yes')->after('candidate_id');
        });

        // Rebuild unique key to allow yes + no rows per candidate.
        Schema::table('manual_tallies', function (Blueprint $table) {
            $table->dropUnique(['election_id', 'candidate_id']);
        });

        Schema::table('manual_tallies', function (Blueprint $table) {
            $table->unique(['election_id', 'candidate_id', 'choice']);
        });

        DB::table('manual_tallies')->whereNull('choice')->update(['choice' => 'yes']);
    }

    public function down(): void
    {
        Schema::table('manual_tallies', function (Blueprint $table) {
            $table->dropUnique(['election_id', 'candidate_id', 'choice']);
        });

        Schema::table('manual_tallies', function (Blueprint $table) {
            $table->dropColumn('choice');
            $table->unique(['election_id', 'candidate_id']);
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex(['election_id', 'position_id', 'choice']);
            $table->dropColumn('choice');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn([
                'unopposed_yes_no',
                'unopposed_threshold_type',
                'unopposed_threshold_value',
                'unopposed_fail_outcome',
                'unopposed_fail_note',
            ]);
        });

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
};
