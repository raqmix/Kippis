<?php

return [
    // Authentication Messages
    'registration_successful' => 'تم التسجيل بنجاح. يرجى التحقق من بريدك الإلكتروني للتحقق من رمز OTP.',
    'account_verified' => 'تم التحقق من الحساب بنجاح.',
    'login_successful' => 'تم تسجيل الدخول بنجاح.',
    'logout_successful' => 'تم تسجيل الخروج بنجاح.',
    'token_refreshed' => 'تم تحديث الرمز المميز بنجاح.',
    'account_deleted' => 'تم حذف الحساب بنجاح.',
    'password_reset_successful' => 'تم إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول بكلمة المرور الجديدة.',
    'otp_sent' => 'إذا كان البريد الإلكتروني موجوداً، تم إرسال رمز OTP إلى عنوان بريدك الإلكتروني.',

    // Error Messages
    'customer_not_found' => 'العميل غير موجود.',
    'invalid_credentials' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
    'account_not_verified' => 'الحساب غير مُتحقق منه. يرجى التحقق من بريدك الإلكتروني أولاً.',
    'otp_invalid' => 'رمز OTP غير صحيح أو منتهي الصلاحية.',
    'email_already_exists' => 'البريد الإلكتروني مستخدم بالفعل.',
    'validation_failed' => 'فشل التحقق.',
    'unauthorized' => 'غير مصرح به. يرجى تسجيل الدخول أولاً.',
    'server_error' => 'حدث خطأ. يرجى المحاولة مرة أخرى لاحقاً.',

    // Field Labels
    'id' => 'المعرف',
    'name' => 'الاسم',
    'email' => 'البريد الإلكتروني',
    'phone' => 'الهاتف',
    'country_code' => 'رمز الدولة',
    'birthdate' => 'تاريخ الميلاد',
    'avatar' => 'الصورة الشخصية',
    'foodics_customer_id' => 'معرف عميل Foodics',
    'is_verified' => 'مُتحقق منه',
    'verified' => 'مُتحقق',
    'unverified' => 'غير مُتحقق',
    'created_at' => 'تاريخ الإنشاء',
    'updated_at' => 'تاريخ التحديث',

    // Foodics Errors
    'foodics_unauthorized' => 'فشل المصادقة مع Foodics. يرجى التحقق من بيانات الاعتماد.',
    'foodics_forbidden' => 'الوصول محظور إلى مورد Foodics.',
    'foodics_not_found' => 'المورد غير موجود في Foodics.',
    'foodics_validation' => 'خطأ في التحقق من Foodics.',
    'foodics_rate_limit' => 'طلبات كثيرة جداً إلى Foodics. يرجى المحاولة لاحقاً.',
    'foodics_server_error' => 'خطأ في خادم Foodics. يرجى المحاولة مرة أخرى لاحقاً.',
    'foodics_maintenance' => 'Foodics قيد الصيانة حالياً.',
    'foodics_timeout' => 'انتهت مهلة الطلب إلى Foodics.',
    'foodics_mapping_error' => 'خطأ في تعيين بيانات Foodics إلى النموذج المحلي.',

    // Cart & Order Messages
    'cart_initialized' => 'تم تهيئة السلة بنجاح.',
    'item_added' => 'تمت إضافة العنصر إلى السلة.',
    'item_updated' => 'تم تحديث العنصر.',
    'item_removed' => 'تمت إزالة العنصر من السلة.',
    'promo_applied' => 'تم تطبيق رمز الخصم بنجاح.',
    'promo_removed' => 'تمت إزالة رمز الخصم.',
    'cart_abandoned' => 'تم وضع علامة على السلة كمتروكة.',
    'cart_empty' => 'السلة فارغة.',
    'cart_not_found' => 'السلة غير موجودة.',
    'invalid_promo_code' => 'رمز الخصم غير صحيح أو منتهي الصلاحية.',
    'minimum_order_not_met' => 'لم يتم استيفاء الحد الأدنى لمبلغ الطلب.',
    'order_created' => 'تم إنشاء الطلب بنجاح.',
    'cart_recreated' => 'تم إعادة إنشاء السلة من الطلب.',

    // QR Receipt Messages
    'receipt_submitted' => 'تم إرسال الإيصال بنجاح.',

    // Error Messages
    'product_not_found' => 'المنتج غير موجود.',
    'order_not_found' => 'الطلب غير موجود.',
];
