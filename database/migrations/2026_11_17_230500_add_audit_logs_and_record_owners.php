<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 40);
                $table->string('subject_type', 80)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('summary', 500);
                $table->json('payload')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->index(['action', 'created_at']);
            });
        }

        $this->owner('restaurant_reservations');
        $this->owner('restaurant_reservation_items');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        foreach (['restaurant_reservations', 'restaurant_reservation_items'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'created_by')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropConstrainedForeignId('created_by');
                });
            }
        }
    }

    private function owner(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'created_by')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });
    }
};
