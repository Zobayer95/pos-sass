<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff')->after('email');

            $table->dropUnique(['email']);
            $table->unique(['tenant_id', 'email']);
            $table->index('tenant_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['tenant_id', 'role']);
            $table->unique('email');
        });
    }
};
