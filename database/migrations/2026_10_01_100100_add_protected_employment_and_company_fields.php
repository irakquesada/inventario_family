<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'tax_id')) {
            Schema::table('employees', fn (Blueprint $table) => $table->text('tax_id')->nullable()->after('phone'));
        }
        if (! Schema::hasColumn('employees', 'social_security_number')) {
            Schema::table('employees', fn (Blueprint $table) => $table->text('social_security_number')->nullable()->after('tax_id'));
        }
        if (! Schema::hasColumn('employees', 'postal_address')) {
            Schema::table('employees', fn (Blueprint $table) => $table->text('postal_address')->nullable()->after('social_security_number'));
        }
        if (! Schema::hasColumn('employees', 'iban')) {
            Schema::table('employees', fn (Blueprint $table) => $table->text('iban')->nullable()->after('postal_address'));
        }
        if (! Schema::hasColumn('site_settings', 'legal_business_name')) {
            Schema::table('site_settings', fn (Blueprint $table) => $table->string('legal_business_name')->nullable());
        }
        if (! Schema::hasColumn('site_settings', 'business_tax_id')) {
            Schema::table('site_settings', fn (Blueprint $table) => $table->string('business_tax_id')->nullable());
        }
        if (! Schema::hasColumn('site_settings', 'social_security_account')) {
            Schema::table('site_settings', fn (Blueprint $table) => $table->string('social_security_account')->nullable());
        }
        if (! Schema::hasColumn('site_settings', 'payroll_address')) {
            Schema::table('site_settings', fn (Blueprint $table) => $table->text('payroll_address')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('site_settings', fn (Blueprint $table) => $table->dropColumn(['legal_business_name', 'business_tax_id', 'social_security_account', 'payroll_address']));
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn(['tax_id', 'social_security_number', 'postal_address', 'iban']));
    }
};
