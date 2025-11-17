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
        Schema::create('housing_sidebar_menus', function (Blueprint $table) {
            $table->id('sidebar_menu_id');
            $table->string('menu_name', 255);
            $table->string('route_name', 255)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('icon_class', 100)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('order_no')->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            $table->foreign('parent_id')->references('sidebar_menu_id')->on('housing_sidebar_menus')->onDelete('cascade');
            $table->index(['parent_id', 'is_active', 'order_no']);
        });

        Schema::create('housing_sidebar_menu_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_menu_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->foreign('sidebar_menu_id')->references('sidebar_menu_id')->on('housing_sidebar_menus')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->unique(['sidebar_menu_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housing_sidebar_menu_roles');
        Schema::dropIfExists('housing_sidebar_menus');
    }
};

