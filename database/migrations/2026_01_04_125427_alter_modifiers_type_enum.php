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

        // 1) لو في قيم قديمة، يا إما نحذفها أو نحولها لقيمة جديدة
        // أنا هنا هحذف القديم زي ما أنت كنت عامل (ممكن تعمل update بدل delete لو محتاج)
        DB::table('modifiers')
            ->whereIn('type', ['sweetness', 'fizz', 'caffeine', 'extra'])
            ->delete();

        // 2) اجبر العمود يبقى VARCHAR مؤقتًا (بيحل مشكلة لو كان INT أو ENUM قديم)
        DB::statement("ALTER TABLE modifiers MODIFY COLUMN type VARCHAR(50) NOT NULL");

        // 3) بعد كده حوّله لـ ENUM بالقيم الجديدة فقط
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

        // رجّعه VARCHAR (أو رجعه ENUM قديم لو ده المطلوب عندك)
        DB::statement("ALTER TABLE modifiers MODIFY COLUMN type VARCHAR(50) NOT NULL");
    }
};
