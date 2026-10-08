# نشر «لهيب» على cPanel

المنصة تعمل مباشرة من مستودع Git داخل الاستضافة — لا يوجد Node على الخادم، لذلك
ملفات الواجهة المبنية (`public/build`) **مرفوعة مع الكود**.

- مجلد التطبيق: `/home/laheebsa/repositories/Laheeb`
- جذر الموقع: `/home/laheebsa/public_html` (نسخة من `public/` يعيد توجيهها `.cpanel.yml`)
- PHP: `/opt/cpanel/ea-php83/root/usr/bin/php` (استخدمه دائمًا في Terminal)

## النشر اليومي (بعد كل push)

cPanel ← **Git™ Version Control** ← صف Laheeb ← **Manage** ← تبويب **Pull or Deploy**:

1. **Update from Remote**
2. **Deploy HEAD Commit**

يتكفّل `.cpanel.yml` بالباقي: الترحيلات، الكاشات، ومزامنة جذر الموقع.
تحقّق أن التحديث وصل من **رقم الإصدار** أسفل القائمة الجانبية وصفحة الدخول.

## ملف ‎.env على الخادم (أول تركيب فقط)

انسخ `.env.example` إلى `.env` داخل مجلد التطبيق وعدّل:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://laheeb.sa
APP_TIMEZONE=Asia/Riyadh
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_DATABASE=laheebsa_laheebsa
DB_USERNAME=laheebsa_admin
DB_PASSWORD=********

# البريد يُضبط من داخل المنصة (الإعدادات ← البريد الإلكتروني) — لا تحتاج MAIL_* هنا.
BACKUP_NOTIFY_EMAIL=saud.ayadah@experience.sa
```

ثم في Terminal:

```
cd /home/laheebsa/repositories/Laheeb
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan key:generate --force
$PHP artisan migrate --force
SEED_OWNER_PASSWORD='كلمة-سر-قوية' $PHP artisan db:seed --force
$PHP artisan storage:link
mkdir -p storage/app/mpdf
```

> ⚠️ **لا تشغّل `key:generate` مرة ثانية أبدًا على قاعدة فيها بيانات** — المفتاح يفك
> تشفير كلمة مرور البريد وأرقام الإقامات. وخذ نسخة من `.env` في مكان آمن.

## الجدولة (مرة واحدة)

cron واحد كل دقيقة:

```
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/laheebsa/repositories/Laheeb/artisan schedule:run >> /dev/null 2>&1
```

يشغّل تلقائيًا: المصاريف المتكررة (05:00)، الطابور، تنظيف النسخ (02:45)،
نسخة قاعدة البيانات اليومية (03:00)، نسخة كاملة أسبوعية بالملفات (الأحد 03:30)،
وفحص دفتر الأستاذ `ledger:verify` (السبت 04:00).

## النسخ الاحتياطي والاستعادة

- الأرشيفات في: `storage/app/private/Laheeb/`
- استعادة: `unzip "storage/app/private/Laheeb/<backup>.zip" -d /tmp/restore`
  ثم استورد `db-dumps/mysql-*.sql` عبر phpMyAdmin.
- إشعارات فشل النسخ تصل إلى `BACKUP_NOTIFY_EMAIL` (بعد ضبط البريد من المنصة).

## أعطال شائعة

| العرض | السبب | الحل |
|---|---|---|
| composer/artisan يشتكي من PHP 7.4 | CLI الافتراضي قديم | استخدم `$PHP` الكامل أعلاه |
| فشل النسخ الاحتياطي: mysqldump | غير موجود في PATH | في `.env`: `DB_DUMP_PATH=/usr/bin` |
| صفحة بيضاء بعد نشر | كاش قديم | `$PHP artisan config:cache && $PHP artisan route:cache` |
| رسائل البريد لا تصل | الإعدادات داخل المنصة | الإعدادات ← البريد الإلكتروني ← «أرسل رسالة تجريبية» |
