<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refined_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_report_id')->constrained('generated_reports')->cascadeOnDelete();
            $table->string('mode', 32);
            $table->text('prompt')->nullable();
            $table->string('prompt_hash', 64);
            $table->text('content');
            $table->string('engine')->nullable();
            $table->string('source_signature', 64);
            $table->timestamps();

            $table->unique(
                ['generated_report_id', 'mode', 'prompt_hash'],
                'refined_reports_unique_variant'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refined_reports');
    }
};
