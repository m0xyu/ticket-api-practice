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
        Schema::table('reservations', function (Blueprint $table) {
            // 'pending' (仮予約), 'confirmed' (確定), 'canceled' (期限切れ/キャンセル)
            $table->string('status')->default('pending')->after('user_id')->comment('予約ステータス');
            $table->dateTime('expires_at')->nullable()->after('reserved_at')->comment('有効期限');
            $table->index(['status', 'expires_at'])->comment('ステータスと有効期限のインデックス');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn(['status', 'expires_at']);
        });
    }
};
