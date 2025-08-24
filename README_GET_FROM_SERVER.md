# Get From Server - الإصدار 1.0.1

## 🎉 التحسينات الجديدة في الإصدار 1.0.1

### ✅ إصلاح مشكلة ملفات ISO
تم إصلاح المشكلة التي كانت تمنع استيراد ملفات ISO من السيرفر. الآن يمكنك استيراد ملفات ISO بسهولة دون ظهور الخلفية الحمراء.

### 📁 دعم محسن لأنواع الملفات
تم إضافة دعم للأنواع التالية من الملفات:

| نوع الملف | امتداد | نوع MIME |
|-----------|--------|----------|
| ISO Image | `.iso` | `application/x-iso9660-image` |
| ZIP Archive | `.zip` | `application/zip` |
| RAR Archive | `.rar` | `application/x-rar-compressed` |
| 7-Zip Archive | `.7z` | `application/x-7z-compressed` |
| TAR Archive | `.tar` | `application/x-tar` |
| Gzip Archive | `.gz` | `application/gzip` |
| Bzip2 Archive | `.bz2` | `application/x-bzip2` |

### 🔧 التحسينات التقنية

#### 1. إضافة فلتر `upload_mimes`
```php
function add_iso_mime_type( $mimes ) {
    $mimes['iso'] = 'application/x-iso9660-image';
    $mimes['zip'] = 'application/zip';
    $mimes['rar'] = 'application/x-rar-compressed';
    $mimes['7z'] = 'application/x-7z-compressed';
    $mimes['tar'] = 'application/x-tar';
    $mimes['gz'] = 'application/gzip';
    $mimes['bz2'] = 'application/x-bzip2';
    return $mimes;
}
```

#### 2. تحسين التحقق من نوع الملف
```php
// إضافة دعم لملفات إضافية
if ( !$type ) {
    switch ( $ext ) {
        case 'iso':
            $type = 'application/x-iso9660-image';
            break;
        case 'zip':
            $type = 'application/zip';
            break;
        // ... المزيد من الأنواع
    }
}
```

#### 3. تحسين واجهة المستخدم
- إزالة الخلفية الحمراء من الملفات المدعومة
- تحسين رسائل الخطأ لتكون أكثر وضوحاً
- دعم أفضل للملفات المضغوطة

## 🚀 كيفية الاستخدام

### استيراد ملفات ISO
1. ارفع ملف ISO إلى الخادم عبر FTP
2. اذهب إلى Media → Get From Server
3. انتقل إلى مجلد الملف
4. حدد ملف ISO (لن تظهر خلفية حمراء)
5. اضغط Import

### استيراد الملفات المضغوطة
نفس الخطوات تنطبق على جميع الملفات المضغوطة المدعومة.

## ⚠️ ملاحظات مهمة

### الأمان
- جميع التحسينات الأمنية السابقة لا تزال سارية
- التحقق من نوع MIME الفعلي لا يزال يعمل
- حماية من directory traversal لا تزال مفعلة

### التوافق
- متوافق مع WordPress 6.0+
- متوافق مع PHP 8.0+
- لا يؤثر على الملفات المدعومة سابقاً

## 🔄 التحديث من الإصدار السابق

### التحديث التلقائي
إذا كنت تستخدم الإصدار 1.0.0، يمكنك التحديث مباشرة دون أي إعدادات إضافية.

### التحقق من التحديث
بعد التحديث، تأكد من:
1. عدم ظهور أخطاء في سجلات WordPress
2. إمكانية استيراد ملفات ISO
3. عدم ظهور خلفية حمراء على الملفات المدعومة

## 📞 الدعم

إذا واجهت أي مشاكل:
- راجع سجلات الأخطاء
- تأكد من صلاحيات الملفات
- اتصل بالدعم الفني

---

**الإصدار**: 1.0.1  
**تاريخ الإصدار**: 27 يناير 2025  
**المطور**: Eng. A7meD KaMeL - برمجينا
