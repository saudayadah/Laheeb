# نشر المرحلة 1 على cPanel — Milestone 1 cPanel deployment

> هذه خطوات النشر المختصرة للمرحلة 1. دليل النشر الكامل (`DEPLOY.md`) يُسلَّم في مرحلة التقوية (8).

## المتطلبات على حساب cPanel
- PHP **8.3** من "Select PHP Version" مع الإضافات: `bcmath, intl, mbstring, pdo_mysql, gd, zip, fileinfo, curl, openssl`.
- قاعدة بيانات MySQL 8 / MariaDB 10.6+ بترميز `utf8mb4_unicode_ci` (من MySQL Databases: أنشئ قاعدة + مستخدمًا واربطهما بكل الصلاحيات).

## 1) البناء محليًا (لا يوجد Node على الخادم)
```bash
npm ci
npm run build        # ينتج public/build
composer install --no-dev --optimize-autoloader
```

## 2) رفع الملفات
- ارفع المشروع كاملًا (بما فيه `vendor/` و`public/build/`) إلى مجلد خارج `public_html`، مثلًا `~/laheeb`.
- وجّه docroot للنطاق/النطاق الفرعي إلى `~/laheeb/public` (من Domains ‑> Document Root).
- **إن تعذّر تغيير docroot:** انسخ محتويات `public/` إلى `public_html/` وعدّل في `public_html/index.php` السطرين:
  ```php
  require __DIR__.'/../laheeb/vendor/autoload.php';
  $app = require_once __DIR__.'/../laheeb/bootstrap/app.php';
  ```
  ولا ترفع `.env` أو `storage/` أو `vendor/` داخل `public_html` إطلاقًا.

## 3) الإعداد
أنشئ `~/laheeb/.env` نسخة من `.env.example` وعدّل:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_LOCALE=ar
APP_TIMEZONE=Asia/Riyadh
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=cpaneluser_laheeb
DB_USERNAME=cpaneluser_laheeb
DB_PASSWORD=********
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

## 4) أوامر التهيئة (Terminal في cPanel)
```bash
cd ~/laheeb
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force   # الأدوار + المنتجات + حساب المدير
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
> `DatabaseSeeder` في بيئة production يزرع الأدوار والمنتجات وحساب المدير فقط (بدون بيانات تجريبية).
> لتجربة النظام ببيانات العرض: `php artisan db:seed --class=Database\\Seeders\\DemoSeeder --force`.

## 5) الكرون (مطلوب من الآن للطوابير لاحقًا)
في Cron Jobs أضف سطرًا واحدًا كل دقيقة:
```
* * * * * /usr/local/bin/php /home/USER/laheeb/artisan schedule:run >> /dev/null 2>&1
```

## 6) أول دخول
- افتح الموقع ← سجّل بـ `owner@laheeb.test` / `password`.
- **فورًا:** من «المستخدمون» غيّر بريد المدير وكلمة المرور.
- من «الإعدادات» أدخل الاسم الرسمي والرقم الضريبي والسجل التجاري والعنوان الوطني.
- من «استيراد البيانات» نزّل قالب العملاء وعبّئه من الإكسل الحالي ثم ارفعه (معاينة ← اعتماد).

## التحقق — Verification checklist
- [ ] `https://yourdomain.com/up` يعيد 200.
- [ ] صفحة الدخول تظهر بالعربي RTL وبخط IBM Plex Sans Arabic.
- [ ] الدخول كمدير يعمل، وشاشة العملاء تعرض وتبحث وترقّم الصفحات.
- [ ] إنشاء عميل بسعر، ثم تعديل سعر بتاريخ سريان مستقبلي، يظهران في سجل الأسعار.
- [ ] استيراد ملف تجريبي من القالب ينجح بمعاينة ثم اعتماد.
- [ ] `.env` و `storage/` غير قابلة للوصول من المتصفح (جرّب `https://yourdomain.com/.env` ← يجب أن تُرفض).
