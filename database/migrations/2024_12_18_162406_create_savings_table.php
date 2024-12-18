<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name');
            $table->integer('target_amount');
            $table->enum('saving_frequency', ['harian', 'mingguan', 'bulanan']);
            $table->integer('nominal_per_frequency');
            $table->integer('current_savings');
            $table->integer('remaining_amount');
            $table->integer('remaining_days');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->enum('status', ['tercapai', 'berlangsung']);
            $table->string('image');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings');
    }
};
