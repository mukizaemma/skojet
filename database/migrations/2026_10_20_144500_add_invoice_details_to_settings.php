<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('invoice_legal_name')->nullable()->after('company');
            $table->text('invoice_address')->nullable()->after('invoice_legal_name');
            $table->string('invoice_phone', 40)->nullable()->after('invoice_address');
            $table->string('invoice_email')->nullable()->after('invoice_phone');
            $table->string('invoice_tax_id', 80)->nullable()->after('invoice_email');
            $table->text('invoice_footer')->nullable()->after('invoice_tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_legal_name',
                'invoice_address',
                'invoice_phone',
                'invoice_email',
                'invoice_tax_id',
                'invoice_footer',
            ]);
        });
    }
};
