<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        $mapping = [
            '$' => 'usd',
            "\u{20AC}" => 'eur',
            'EUR' => 'eur',
            "\u{00A3}" => 'gbp',
            'GBP' => 'gbp',
            "\u{00A5}" => 'jpy',
            'JPY' => 'jpy',
        ];

        foreach ($mapping as $symbol => $code) {
            DB::table('plans')->where('currency', $symbol)->update(['currency' => $code]);
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->string('currency', 3)->default('usd')->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('currency', 3)->default('$')->change();
        });

        DB::table('plans')->where('currency', 'usd')->update(['currency' => '$']);
        DB::table('plans')->where('currency', 'eur')->update(['currency' => "\u{20AC}"]);
        DB::table('plans')->where('currency', 'gbp')->update(['currency' => "\u{00A3}"]);
        DB::table('plans')->where('currency', 'jpy')->update(['currency' => "\u{00A5}"]);
    }
};
