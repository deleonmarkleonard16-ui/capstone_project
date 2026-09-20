<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function statuses(array $values, string $default): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) use ($values, $default) {
            $table->enum('status', $values)->default($default)->change();
        });
    }

    public function up(): void
    {
        $values = ['Pending Payment', 'Receipt Uploaded', 'Approved', 'In-Progress', 'Completed'];
        $this->statuses([...$values, 'Pending'], 'Pending Payment');
        DB::table('guidance_appointments')->where('status', 'Pending')->whereNotNull('payment_slip_path')->where('payment_slip_path', '!=', '')
            ->update(['status' => 'Receipt Uploaded']);
        DB::table('guidance_appointments')->where('status', 'Pending')->update(['status' => 'Pending Payment']);
        // Historical upload times were not stored separately; preserve their last known update time.
        DB::table('guidance_appointments')->where('status', 'Receipt Uploaded')->whereNull('appointment_at')
            ->update(['appointment_at' => DB::raw('updated_at')]);
        $this->statuses($values, 'Pending Payment');
    }

    public function down(): void
    {
        $values = ['Pending', 'Approved', 'In-Progress', 'Completed'];
        $this->statuses([...$values, 'Pending Payment', 'Receipt Uploaded'], 'Pending');
        DB::table('guidance_appointments')->whereIn('status', ['Pending Payment', 'Receipt Uploaded'])->update(['status' => 'Pending']);
        $this->statuses($values, 'Pending');
    }
};
