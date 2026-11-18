<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('image')->nullable();
            $table->string('button_label')->nullable();
            $table->string('button_url')->nullable();
            $table->string('placement')->default('all'); // all | home | product | path
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_path')->nullable();
            $table->string('frequency')->default('once'); // once | session | always
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
    }
};
