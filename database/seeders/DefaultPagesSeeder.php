<?php

namespace Database\Seeders;

use App\Core\Models\Page;
use App\Core\Models\PageTranslation;
use Illuminate\Database\Seeder;

class DefaultPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'faq',
                'type' => 'faq',
                'translations' => [
                    'en' => [
                        'title' => 'Frequently Asked Questions',
                        'content' => '<p>This is the FAQ page content. Edit this in the dashboard.</p>',
                    ],
                    'ar' => [
                        'title' => 'الأسئلة الشائعة',
                        'content' => '<p>هذا محتوى صفحة الأسئلة الشائعة. قم بتعديله من لوحة التحكم.</p>',
                    ],
                ],
            ],
            [
                'slug' => 'terms',
                'type' => 'terms',
                'translations' => [
                    'en' => [
                        'title' => 'Terms & Conditions',
                        'content' => '<p>This is the Terms & Conditions page content. Edit this in the dashboard.</p>',
                    ],
                    'ar' => [
                        'title' => 'الشروط والأحكام',
                        'content' => '<p>هذا محتوى صفحة الشروط والأحكام. قم بتعديله من لوحة التحكم.</p>',
                    ],
                ],
            ],
            [
                'slug' => 'privacy',
                'type' => 'privacy',
                'translations' => [
                    'en' => [
                        'title' => 'Privacy Policy',
                        'content' => '<p>This is the Privacy Policy page content. Edit this in the dashboard.</p>',
                    ],
                    'ar' => [
                        'title' => 'سياسة الخصوصية',
                        'content' => '<p>هذا محتوى صفحة سياسة الخصوصية. قم بتعديله من لوحة التحكم.</p>',
                    ],
                ],
            ],
        ];

        foreach ($pages as $pageData) {
            $page = Page::firstOrCreate(
                ['slug' => $pageData['slug']],
                [
                    'type' => $pageData['type'],
                    'is_active' => true,
                    'version' => 1,
                ]
            );

            foreach ($pageData['translations'] as $locale => $translation) {
                PageTranslation::updateOrCreate(
                    [
                        'page_id' => $page->id,
                        'locale' => $locale,
                    ],
                    [
                        'title' => $translation['title'],
                        'content' => $translation['content'],
                    ]
                );
            }
        }
    }
}

