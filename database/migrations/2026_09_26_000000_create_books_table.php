<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('author');
            $table->string('isbn', 13)->nullable()->unique();
            $table->boolean('owned');
            $table->string('reading_status', 16);
            $table->date('started_on')->nullable();
            $table->date('finished_on')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('loan_borrower')->nullable();
            $table->date('lent_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
