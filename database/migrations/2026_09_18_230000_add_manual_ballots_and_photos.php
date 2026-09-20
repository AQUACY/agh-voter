<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('name');
        });

        Schema::table('voters', function (Blueprint $table) {
            $table->string('vote_channel', 16)->nullable()->after('voted_at');
        });

        Schema::table('elections', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('closes_at');
        });

        Schema::create('manual_tallies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('votes')->default(0);
            $table->timestamps();

            $table->unique(['election_id', 'candidate_id']);
        });

        DB::table('voters')
            ->whereNotNull('voted_at')
            ->whereNull('vote_channel')
            ->update(['vote_channel' => 'digital']);
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_tallies');

        Schema::table('elections', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });

        Schema::table('voters', function (Blueprint $table) {
            $table->dropColumn('vote_channel');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
