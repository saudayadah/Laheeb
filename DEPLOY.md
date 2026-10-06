# دليل النشر الكامل على cPanel — Laheeb Platform

> هذا الدليل يفترض استضافة cPanel مشتركة: بدون Redis أو Supervisor أو Node على الخادم.
> كل البناء يتم محليًا، والخادم يستقبل ملفات جاهزة.

---

## 0) المتطلبات على الحساب

| المتطلب | القيمة |
|---|---|
| PHP (من Select PHP Version) | **8.3** |
| إضافات PHP | `bcmath, intl, mbstring, pdo_mysql, gd, zip, fileinfo, curl, openssl` |
| قاعدة بيانات | MySQL 8 أو MariaDB 10.6+، ترميز `utf8mb4_unicode_ci` |
| أدوات اختيارية | Terminal/SSH + Composer (وإن لم تتوفر فهناك بديل أدناه) |

أنشئ من cPanel: قاعدة بيانات + مستخدمًا لها واربطهما بكل الصلاحيات، واحفظ الاسم/المستخدم/كلمة المرور.

---

## 1) البناء محليًا (على جهازك)

```bash
npm ci
npm run build                               # ينتج public/build
composer install --no-dev --optimize-autoloader
```

ارفع المشروع كاملًا (بما فيه `vendor/` و`public/build/`) إلى مجلد **خارج** `public_html`:
`~/laheeb` — بالضغط (zip) ثم فك الضغط من File Manager أسرع بكثير.

> **لا ترفع أبدًا:** `node_modules/`، `.env` المحلي، `database/database.sqlite`، `tests/`.

---

## 2) توجيه الدومين

**الخيار أ (الأفضل):** من Domains غيّر Document Root للدومين/الساب-دومين إلى:
`/home/USER/laheeb/public`

**الخيار ب (إن مُنع تغيير docroot):**
1. انسخ **محتويات** مجلد `public/` إلى `public_html/`.
2. عدّل في `public_html/index.php` السطرين:
```php
require __DIR__.'/../laheeb/vendor/autoload.php';
$app = require_once __DIR__.'/../laheeb/bootstrap/app.php';
```
3. تأكد أن `laheeb/` نفسه خارج `public_html` — بهذا يستحيل الوصول لـ `.env` أو `storage/` من المتصفح.

---

## 3) ملف البيئة

أنشئ `~/laheeb/.env` (انسخ من `.env.example`) وعدّل:

```env
APP_NAME=Laheeb
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

MAIL_MAILER=log        # أو إعدادات SMTP الاستضافة لتنبيهات النسخ الاحتياطي
```

---

## 4) أوامر التهيئة (Terminal في cPanel)

```bash
cd ~/laheeb
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # الأدوار + المنتجات + فئات المصروفات + حساب المدير (بدون بيانات تجريبية في production)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **بدون Terminal؟** شغّل نفس الأوامر عبر cron مؤقت يعمل مرة واحدة، أو ارفع vendor جاهزًا (الخطوة 1 تكفلت بذلك) ونفّذ الأوامر من "Cron Jobs" بسطر مؤقت ثم احذفه.

ملاحظة الصلاحيات: تأكد أن `storage/` و`bootstrap/cache/` قابلة للكتابة (عادةً تلقائي على cPanel).

---

## 5) الكرون (إلزامي)

سطر واحد فقط في Cron Jobs، كل دقيقة:

```
* * * * * /usr/local/bin/php /home/USER/laheeb/artisan schedule:run >> /dev/null 2>&1
```

هذا السطر يشغّل تلقائيًا:
- معالجة قوائم الانتظار (كل دقيقة، بلا تداخل).
- مسودات المصروفات المتكررة (يوميًا 5:00).
- **النسخ الاحتياطي لقاعدة البيانات** (يوميًا 3:00) وتنظيف القديم (2:45) — الملفات في `storage/app/Laravel/`.
- فحص تطابق دفتر القيود (كل سبت 4:00) — `ledger:verify`.

---

## 6) أول دخول

1. افتح الموقع ← `owner@laheeb.test` / `password`.
2. **فورًا**: من «المستخدمون» غيّر بريد المدير وكلمة مروره، وأنشئ حسابات المحاسب والسائقين الحقيقية.
3. «الإعدادات»: الاسم الرسمي، الرقم الضريبي، السجل، العنوان الوطني.
4. «الإعداد والبيانات ← الاستيراد»: استورد العملاء، ثم المنتجات إن لزم، ثم **الأرصدة الافتتاحية** (ديون العملاء والموردين والسلف) من قوالب إكسل جاهزة للتنزيل.
5. أضف الموظفين من «الموظفون» قبل استيراد أرصدة سلفهم.

---

## 7) التحديثات لاحقًا (نشر عبر cPanel Git)

في الحساب: Git™ Version Control ← Create ← استنسخ المستودع إلى `~/repos/laheeb` واربط فرع الإنتاج.
ملف **`.cpanel.yml`** في جذر المشروع ينفّذ النشر تلقائيًا عند كل Pull/Deploy (انسخ الملفات + migrate + إعادة الكاش).
تذكّر: `public/build` يُبنى محليًا ويُدفع مع الكود (أو يُرفع يدويًا بعد كل `npm run build`).

---

## 8) الاستعادة من نسخة احتياطية

```bash
cd ~/laheeb
php artisan down
unzip storage/app/Laravel/<backup>.zip -d /tmp/restore
mysql -u DB_USER -p DB_NAME < /tmp/restore/db-dumps/mysql-*.sql
php artisan up
```

---

## 9) استكشاف الأخطاء

| العرض | السبب الغالب | الحل |
|---|---|---|
| صفحة 500 بعد النشر | كاش قديم | `php artisan optimize:clear` ثم أعد أوامر الكاش |
| «bootstrap/cache must be writable» | صلاحيات المجلد | `chmod -R 775 bootstrap/cache storage` |
| الطوابير لا تعمل | الكرون غير مضبوط | راجع الخطوة 5 ومسار php الصحيح (`which php`) |
| فشل النسخ الاحتياطي | mysqldump غير موجود في PATH | أضف في `.env`: `DB_DUMP_PATH=/usr/bin` (أو مسار الاستضافة) |
| `/.env` يفتح من المتصفح | docroot خاطئ | راجع الخطوة 2 — يجب أن يشير لـ `public/` فقط |

**فحص سريع للصحة:** `https://yourdomain.com/up` يجب أن يعيد 200.
**فحص الدفاتر يدويًا:** `php artisan ledger:verify`.
