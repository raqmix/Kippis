<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // 1) حوّل العمود إلى VARCHAR مؤقتًا مهما كان نوعه الحالي (int/enum/..)
        DB::statement("ALTER TABLE modifiers MODIFY COLUMN type VARCHAR(50) NOT NULL");

        // 2) احذف أي قيم قديمة غير مسموح بها قبل تحويله إلى ENUM جديد
        DB::table('modifiers')
            ->whereNotIn('type', ['size', 'smothing', 'customize_modifires'])
            ->delete();

        // 3) حوّله إلى ENUM بالقيم الجديدة فقط
        DB::statement("
            ALTER TABLE modifiers
            MODIFY COLUMN type ENUM('size','smothing','customize_modifires') NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // رجّعه VARCHAR (أو رجّعه ENUM القديم لو عايز)
        DB::statement("ALTER TABLE modifiers MODIFY COLUMN type VARCHAR(50) NOT NULL");
    }
};
