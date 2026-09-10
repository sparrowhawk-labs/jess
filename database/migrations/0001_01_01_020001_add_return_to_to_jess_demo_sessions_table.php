<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the banner's exit should land for this session: the Referer of
     * the signed-link hit that created it (the page the demo link was
     * pressed on — possibly an external origin such as a review page), or
     * null when the browser sent none.
     */
    public function up(): void
    {
        Schema::table('jess_demo_sessions', function (Blueprint $table) {
            $table->text('return_to')->nullable()->after('demo_db_path');
        });
    }

    public function down(): void
    {
        Schema::table('jess_demo_sessions', function (Blueprint $table) {
            $table->dropColumn('return_to');
        });
    }
};
