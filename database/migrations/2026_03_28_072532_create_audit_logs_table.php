<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();

        // who did the action
        $table->foreignId('user_id')->constrained()->onDelete('cascade');

        // action type (create, update, delete)
        $table->string('action');

        // what module (staff, patient, visit)
        $table->string('module');

        // description (free text)
        $table->text('description');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
