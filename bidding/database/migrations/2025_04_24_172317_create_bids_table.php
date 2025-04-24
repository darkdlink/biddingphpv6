<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('bid_number')->unique();
            $table->string('source_url')->nullable();
            $table->foreignId('bid_category_id')->constrained();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->timestamp('opening_date');
            $table->timestamp('closing_date');
            $table->string('status');
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bids');
    }
};
