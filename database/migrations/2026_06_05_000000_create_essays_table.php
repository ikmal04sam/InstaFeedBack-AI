<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('essays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('sentence_count')->default(0);
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('level')->default('Weak');
            $table->json('analysis');
            $table->json('feedback');
            $table->json('suggestions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('essays');
    }
};
