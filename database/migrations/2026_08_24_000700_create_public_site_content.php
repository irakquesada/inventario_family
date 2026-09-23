<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('featured_on_landing')->default(false)->index()->after('is_sellable');
        });

        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('hero_title');
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_cta')->default('Reservar');
            $table->string('about_title')->default('Conócenos');
            $table->text('about_text')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('map_embed_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->boolean('reservations_enabled')->default(true);
            $table->boolean('testimonials_enabled')->default(true);
            $table->boolean('jobs_enabled')->default(false);
            $table->string('jobs_email')->nullable();
            $table->timestamps();
        });
        DB::table('site_settings')->insert([
            'hero_title' => config('brand.name'),
            'hero_subtitle' => 'Productos, atención y experiencias para disfrutar.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_name');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('comment');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Permission::findOrCreate('content.manage');
        Role::findOrCreate('admin')->givePermissionTo('content.manage');
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('site_settings');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('featured_on_landing');
        });
        Permission::query()->where('name', 'content.manage')->delete();
    }
};
