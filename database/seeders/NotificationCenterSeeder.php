<?php

namespace Database\Seeders;

use App\Core\Models\Admin;
use App\Core\Models\NotificationCenter;
use Illuminate\Database\Seeder;

class NotificationCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first admin or create a test one
        $admin = Admin::first();
        
        if (!$admin) {
            $this->command->warn('No admin found. Please create an admin first.');
            return;
        }

        // Create 4 demo notifications as unread
        $notifications = [
            [
                'user_id' => $admin->id,
                'type' => 'new_booking',
                'title' => 'حجوزات جديدة',
                'body' => 'لقد تلقيت 5 حجوزات جديدة اليوم، بما في ذلك 3 قصات شعر و 2 جلسات تصفيف.',
                'icon' => 'heroicon-o-clock',
                'color' => 'light-green',
                'is_read' => false,
                'action_url' => null,
            ],
            [
                'user_id' => $admin->id,
                'type' => 'low_stock',
                'title' => 'مخزون منخفض من المنتجات',
                'body' => '3 منتجات تنفد من المخزون: شامبو زيت الأرغان (5 يسار)، بلسم اللحية (2 يسار)، وجل التصفيف (3 يسار).',
                'icon' => 'heroicon-o-view-grid',
                'color' => 'light-green',
                'is_read' => false,
                'action_url' => null,
            ],
            [
                'user_id' => $admin->id,
                'type' => 'staff_absence',
                'title' => 'غياب الموظفين القادم',
                'body' => 'حدد 2 من الموظفين أنفسهم غير متاحين ليوم غد: أليكس (يوم كامل) وبريا (12 ظهرا - 6 مساء).',
                'icon' => 'heroicon-o-users',
                'color' => 'light-green',
                'is_read' => false,
                'action_url' => null,
            ],
            [
                'user_id' => $admin->id,
                'type' => 'new_reviews',
                'title' => 'تم إرسال مراجعات جديدة',
                'body' => 'لقد تلقيت 2 من مراجعات العملاء الجدد خلال الـ 24 ساعة الماضية.',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'color' => 'light-green',
                'is_read' => false,
                'action_url' => null,
            ],
        ];

        foreach ($notifications as $notification) {
            NotificationCenter::create($notification);
        }

        $this->command->info('Created 4 demo notifications for NotificationCenter.');
    }
}

