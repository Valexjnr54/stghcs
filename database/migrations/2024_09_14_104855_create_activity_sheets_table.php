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
        Schema::create('activity_sheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_worker_id')->constrained()->onDelete('cascade');
            $table->foreign('support_worker_id')->references('id')->on('users');
            $table->unsignedBigInteger('gig_id')->constrained()->onDelete('cascade');;
            $table->foreign('gig_id')->references('id')->on('gigs');
            $table->unsignedBigInteger('client_id')->constrained()->onDelete('cascade');;
            $table->foreign('client_id')->references('id')->on('clients');
            $table->string('timesheet_id');
            $table->foreign('timesheet_id')->references('unique_id')->on('time_sheets')->onDelete('cascade');
            $table->string('activity_id');
            $table->json('activity_sheet');
            $table->text('comment')->nullable();
            $table->string('activity_time');
            $table->string('activity_day');
            $table->string('activity_date');
            $table->string('activity_week_number');
            $table->string('activity_year');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_sheets');
    }
};
