<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->uuid('reader_id');
            $table->dropUnique(['isbn']);
            $table->unique(['reader_id', 'isbn']);
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropUnique(['reader_id', 'isbn']);
            $table->unique(['isbn']);
            $table->dropColumn('reader_id');
        });
    }
};
