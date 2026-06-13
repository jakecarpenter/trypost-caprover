<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Structured brand voice — a JSON array of BrandVoiceTrait values
            // (e.g. ["third_person", "balanced", "no_hype"]), replacing BOTH the
            // free-text `brand_voice_notes` and the single `brand_tone` column.
            $table->json('brand_voice_traits')->nullable()->after('brand_description');
            $table->dropColumn(['brand_voice_notes', 'brand_tone']);
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('brand_voice_traits');
            $table->string('brand_tone')->default('professional')->after('brand_description');
            $table->text('brand_voice_notes')->nullable()->after('brand_tone');
        });
    }
};
