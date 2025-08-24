=== Get From Server ===
Contributors: brmgina
Tags: admin, media, uploads, post, import, files, server, ftp
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin to allow the Media Manager to get files from the webservers filesystem.

== Description ==

**Get From Server** هي إضافة ووردبريس جديدة ومتطورة تم تطويرها لحل مشاكل الاستضافة السيئة وقيود رفع الملفات. تسمح هذه الإضافة للمستخدمين برفع الملفات الكبيرة عبر FTP أو SSH ثم استيرادها بسهولة إلى مكتبة الوسائط في ووردبريس.

= الميزات الرئيسية =

* **الأمان المتقدم**: حماية شاملة من هجمات directory traversal
* **إدارة ذكية للملفات**: واجهة مستخدم حديثة وسهلة الاستخدام
* **دعم محسن لأنواع الملفات**: ISO, ZIP, RAR, 7Z, TAR, GZ, BZ2
* **الأداء المحسن**: معالجة محسنة للملفات والمجلدات

= الاستخدام =

1. ارفع الملفات عبر FTP إلى الخادم
2. اذهب إلى Media → Get From Server
3. انتقل إلى مجلد الملفات
4. حدد الملفات المراد استيرادها
5. اضغط Import

== Installation ==

1. انسخ جميع ملفات الإضافة إلى مجلد `wp-content/plugins/get-from-server/`
2. فعّل الإضافة من لوحة التحكم
3. اذهب إلى Media → Get From Server

== Frequently Asked Questions ==

= لماذا الملف الذي أريد استيراده له خلفية حمراء؟ =

ووردبريس يسمح فقط بأنواع معينة من الملفات لأسباب أمنية. الإضافة تدعم الآن تلقائياً ملفات ISO, ZIP, RAR, 7Z, TAR, GZ, و BZ2.

= أين يتم حفظ الملفات المستوردة؟ =

إذا كان الملف خارج مجلد الرفع القياسي، سيتم نسخه إلى مجلد الرفع الحالي. إذا كان موجوداً بالفعل داخل مجلد الرفع، سيتم استخدامه كما هو.

== Screenshots ==

1. واجهة الإضافة الرئيسية
2. قائمة الملفات والمجلدات
3. عملية الاستيراد

== Changelog ==

= 1.0.1 =
* إضافة دعم لملفات ISO والملفات المضغوطة
* إصلاح مشكلة الخلفية الحمراء
* تحسين رسائل الخطأ

= 1.0.0 =
* أول إصدار مستقر
* ميزات أمنية شاملة
* دعم كامل لـ WordPress الحديث

== Upgrade Notice ==

= 1.0.1 =
هذا التحديث يضيف دعم لملفات ISO والملفات المضغوطة الأخرى. يوصى بالتحديث.

== Developer ==

هذه الإضافة مطورة بواسطة **Eng. A7meD KaMeL** من فريق **برمجينا**.

== Support ==

* **GitHub**: https://github.com/Brmgina/get-from-server/
* **واتساب**: https://wa.me/201556000180
