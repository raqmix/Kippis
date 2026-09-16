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
            [
                'slug' => 'refund',
                'type' => 'refund',
                'translations' => [
                    'en' => [
                        'title' => 'Refund & Cancellation Policy',
                        'content' => self::refundPolicyEn(),
                    ],
                    'ar' => [
                        'title' => 'سياسة الاسترداد والإلغاء',
                        'content' => self::refundPolicyAr(),
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

    private static function refundPolicyEn(): string
    {
        return <<<'HTML'
<p><em>Last updated: 17 June 2026</em></p>

<p>Thanks for choosing Kippis. Because every drink is freshly prepared to order, our refund and cancellation rules are tailored to a made-to-order food &amp; beverage experience. Please read carefully before placing an order.</p>

<h2>1. Order Cancellation</h2>
<ul>
  <li><strong>Before preparation starts</strong> — you may cancel any order free of charge from the order screen as long as the order is still in <em>Pending</em> or <em>Confirmed</em> status. Card and Apple Pay charges are released automatically.</li>
  <li><strong>After preparation starts</strong> — once the order moves to <em>Preparing</em>, it can no longer be cancelled because ingredients have already been used.</li>
  <li><strong>Cash orders</strong> can be cancelled at any time before pickup; nothing is charged.</li>
</ul>

<h2>2. Refunds for Quality Issues</h2>
<p>If your order has a quality issue (wrong item, missing item, spilled or damaged drink, or a clear food-safety concern), contact our support team within <strong>24 hours</strong> of receipt:</p>
<ul>
  <li>In-app: <em>Profile → Support</em></li>
  <li>Email: <a href="mailto:support@kippis-eg.com">support@kippis-eg.com</a></li>
</ul>
<p>Please include your order number and, where possible, a photo. Eligible cases will be resolved with one of the following, at our discretion:</p>
<ul>
  <li>Replacement of the affected item on your next order</li>
  <li>Store credit or loyalty point compensation</li>
  <li>Refund to the original payment method</li>
</ul>

<h2>3. Items We Cannot Refund</h2>
<ul>
  <li>Personal taste preference (e.g. "I didn't like the flavour"). We're happy to suggest a different recipe for next time.</li>
  <li>Items where preparation has already started and no quality issue has been reported.</li>
  <li>Delays caused by third-party delivery partners outside of our control. We will mediate but the courier remains responsible for delivery quality.</li>
</ul>

<h2>4. Refund Method &amp; Timing</h2>
<ul>
  <li><strong>Card</strong> — refunded to the original card via the payment gateway. Funds typically appear within 7–14 business days, depending on your bank.</li>
  <li><strong>Apple Pay</strong> — refunded to the card backing the Apple Pay token. Same 7–14 business-day window.</li>
  <li><strong>Cash</strong> — store credit added to your Kippis wallet, or a cash refund collected at the branch where the order was placed.</li>
</ul>

<h2>5. Promo Codes &amp; Loyalty Points</h2>
<p>If a refunded order used a promo code or loyalty points, we will, where technically possible, restore the promo code redemption and refund the points to your wallet. One-time-use codes that have already been consumed may not be reusable but a fresh code of equal value will be issued instead.</p>

<h2>6. Squad &amp; Split Orders</h2>
<p>For Squad orders with split payments, refunds are calculated per member's share and returned to each member's original payment method.</p>

<h2>7. Contact</h2>
<p>For any question about a refund or cancellation, reach us via in-app Support, by email at <a href="mailto:support@kippis-eg.com">support@kippis-eg.com</a>, or via the contact details on our <a href="/support">Support page</a>.</p>
HTML;
    }

    private static function refundPolicyAr(): string
    {
        return <<<'HTML'
<p><em>آخر تحديث: 17 يونيو 2026</em></p>

<p>شكرًا لاختيارك Kippis. نظرًا لأن كل مشروب يُحضَّر طازجًا حسب طلبك، فإن قواعد الاسترداد والإلغاء لدينا مصممة لتجربة مشروبات وأطعمة تُعَدّ عند الطلب. يرجى قراءة هذه السياسة بعناية قبل تقديم طلبك.</p>

<h2>1. إلغاء الطلب</h2>
<ul>
  <li><strong>قبل بدء التحضير</strong> — يمكنك إلغاء أي طلب مجانًا من شاشة الطلب طالما أن الطلب لا يزال في حالة <em>قيد الانتظار</em> أو <em>تم التأكيد</em>. تُحرَّر مبالغ الدفع بالبطاقة و Apple Pay تلقائيًا.</li>
  <li><strong>بعد بدء التحضير</strong> — بمجرد انتقال الطلب إلى حالة <em>قيد التحضير</em>، لا يمكن إلغاؤه لأن المكونات قد استُخدمت بالفعل.</li>
  <li><strong>الطلبات النقدية</strong> يمكن إلغاؤها في أي وقت قبل الاستلام دون أي رسوم.</li>
</ul>

<h2>2. الاسترداد بسبب مشاكل الجودة</h2>
<p>إذا كان طلبك يحتوي على مشكلة في الجودة (صنف خاطئ، صنف ناقص، مشروب مسكوب أو تالف، أو مشكلة سلامة غذائية واضحة)، تواصل مع فريق الدعم خلال <strong>24 ساعة</strong> من الاستلام:</p>
<ul>
  <li>من داخل التطبيق: <em>الملف الشخصي ← الدعم</em></li>
  <li>البريد الإلكتروني: <a href="mailto:support@kippis-eg.com">support@kippis-eg.com</a></li>
</ul>
<p>يرجى تضمين رقم الطلب وصورة إن أمكن. تُحَلّ الحالات المؤهَّلة بإحدى الطرق التالية وفقًا لتقديرنا:</p>
<ul>
  <li>استبدال الصنف المتأثر في طلبك التالي</li>
  <li>رصيد متجر أو تعويض بنقاط الولاء</li>
  <li>استرداد إلى طريقة الدفع الأصلية</li>
</ul>

<h2>3. الحالات التي لا يمكن استرداد المبلغ فيها</h2>
<ul>
  <li>التفضيلات الشخصية للذوق (مثل: "لم تعجبني النكهة"). يسعدنا اقتراح وصفة مختلفة في المرة القادمة.</li>
  <li>الأصناف التي بدأ تحضيرها بالفعل ولم يُبلَّغ عن مشكلة جودة فيها.</li>
  <li>التأخيرات الناتجة عن شركاء التوصيل من الأطراف الخارجية والخارجة عن سيطرتنا. سنتوسط لكن شركة التوصيل تظل المسؤولة عن جودة التوصيل.</li>
</ul>

<h2>4. طريقة وتوقيت الاسترداد</h2>
<ul>
  <li><strong>البطاقة</strong> — يُعاد المبلغ إلى البطاقة الأصلية عبر بوابة الدفع. عادةً ما تظهر الأموال خلال 7–14 يوم عمل حسب البنك.</li>
  <li><strong>Apple Pay</strong> — يُعاد المبلغ إلى البطاقة المرتبطة بـ Apple Pay. نفس النافذة الزمنية 7–14 يوم عمل.</li>
  <li><strong>النقدي</strong> — يُضاف رصيد متجر إلى محفظة Kippis الخاصة بك، أو يُسترَد المبلغ نقدًا من الفرع الذي قُدِّم منه الطلب.</li>
</ul>

<h2>5. أكواد الخصم ونقاط الولاء</h2>
<p>إذا كان الطلب المُسترَد قد استخدم كود خصم أو نقاط ولاء، فسنقوم — حيثما كان ذلك ممكنًا تقنيًا — باستعادة استخدام كود الخصم ورد النقاط إلى محفظتك. الأكواد ذات الاستخدام الواحد التي تم استهلاكها قد لا تكون قابلة لإعادة الاستخدام، وفي هذه الحالة سنُصدر كودًا جديدًا بنفس القيمة.</p>

<h2>6. طلبات Squad والدفع المقسَّم</h2>
<p>بالنسبة لطلبات Squad ذات الدفع المقسَّم، يُحسَب الاسترداد على أساس حصة كل عضو ويُعاد إلى طريقة الدفع الأصلية لكل عضو.</p>

<h2>7. التواصل</h2>
<p>لأي استفسار حول الاسترداد أو الإلغاء، تواصل معنا عبر الدعم داخل التطبيق، أو عبر البريد الإلكتروني <a href="mailto:support@kippis-eg.com">support@kippis-eg.com</a>، أو عبر بيانات التواصل في <a href="/support">صفحة الدعم</a>.</p>
HTML;
    }
}

