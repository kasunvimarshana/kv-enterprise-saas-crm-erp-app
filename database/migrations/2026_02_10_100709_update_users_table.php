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
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')->after('id');
            $table->uuid('organization_id')->after('tenant_id');
            $table->string('status', 20)->default('pending')->after('password');
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            
            $table->unique(['tenant_id', 'email']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['organization_id']);
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropIndex(['status']);
            $table->dropColumn(['tenant_id', 'organization_id', 'status']);
        });
    }
};
