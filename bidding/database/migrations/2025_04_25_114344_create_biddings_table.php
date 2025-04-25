<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('biddings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('bidding_number')->unique();
            $table->text('description')->nullable();
            $table->foreignId('company_id')->constrained();
            $table->string('modality'); // pregão, concorrência, tomada de preço, etc
            $table->string('status')->default('pending'); // pending, active, finished, canceled
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->dateTime('publication_date')->nullable();
            $table->dateTime('opening_date');
            $table->dateTime('closing_date')->nullable();
            $table->string('url_source')->nullable(); // URL da fonte da licitação
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biddings');
    }
};
