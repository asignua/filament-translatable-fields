<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->json('items')->nullable();
            $table->json('blocks')->nullable();
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->json('heading')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }
};
