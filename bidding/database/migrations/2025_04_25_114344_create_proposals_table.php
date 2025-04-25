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
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidding_id')->constrained();
            $table->decimal('value', 15, 2);
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft, submitted, won, lost
            $table->decimal('profit_margin', 5, 2)->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->dateTime('submission_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
