<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_ballot_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained()->cascadeOnDelete();
            $table->string('serial', 24);
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique('serial');
            $table->unique(['election_id', 'voter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_ballot_serials');
    }
};
