<p align="center">
  <h1 align="center">نظام إدارة المستودعات والمخزون | Warehouse Management System (WMS)</h1>
  <p align="center">
    نظام إلكتروني متكامل ومتطور لإدارة المستودعات، تتبع الشحنات والتشغيلات، عمليات الجرد، والنقل بين المخازن مع دعم كامل للغة العربية والماسح الضوئي.
  </p>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13" />
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3+" />
  <img src="https://img.shields.io/badge/TailwindCSS-Custom_CSS-38BDF8?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="TailwindCSS" />
  <img src="https://img.shields.io/badge/Language-Arabic_RTL-green?style=for-the-badge" alt="Arabic RTL" />
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="License" />
</p>

---

## نبذة عن المشروع

**نظام إدارة المستودعات (WMS)** هو تطبيق ويب حديث مخصص للمؤسسات والشركات لتسهيل متابعة المخزون بدقة عالية وتقليل الأخطاء البشرية. يوفر النظام حلولاً شاملة تبتدئ من تسليم البضائع الواردة، تتبع التشغيلات (Batches) وتواريخ الصلاحية، إدارة عمليات النقل بين عدة مستودعات، وصولاً إلى طباعة ملصقات الباركود والجرد الدوري التلقائي.

---

## المميزات الرئيسية

### 1. إدارة المستودعات المتعددة (Multi-Warehouse)
- إضافة وإدارة مستودعات متعددة وتحديد السعة الاستيعابية والموقع الجغرافي.
- متابعة الرصيد الفعلي المتاح والمحجوز لكل مستودع بشكل منفصل.

### 2. التحويلات والنقل بين المستودعات (Inter-Warehouse Transfers)
- دورة عمل كاملة تشمل: طلب النقل -> الشحن والتصدير (Dispatch) -> التأكيد والاستلام (Receive).
- تتبع حالة الشحنات وتسجيل الكميات المستلمة والمفقودة تلقائياً.

### 3. إدارة الأصناف والتشغيلات (Items & Batches)
- إدارة الأصناف بالتفصيل (الوحدات، التصنيفات، الحد الأدنى لإعادة الطلب).
- تتبع رقم الدفعة/التشغيلة (Batch Number) وتاريخ الانتهاء (Expiry Date).
- **نظام الحجر الصحي (Quarantine):** إمكانية إيقاف التشغيلات المنتهية أو التالفة مؤقتاً لتجنب صرفها.

### 4. إدارة الحركات المخزنية (Transactions)
- تسجيل عمليات **الوارد (Inbound)**، **الصادر (Outbound)**، **المرتجع (Returns)**، و**التالف (Damaged)**.
- توثيق بيانات الموردين (Suppliers) والعملاء (Customers) المرتبطين بكل حركة.

### 5. الجرد وتدقيق المخزون (Inventory Audits & Reconciliation)
- إنتاج أوامر جرد دوري مفاجئ أو مجدول.
- إدخال الكميات الفعلية ومقارنتها بالرصيد الدفتري للنظام.
- تسوية الفروقات (Reconciliation) مع تعديل المخزون بضغطة زر وتوثيق الفروق.

### 6. الماسح الضوئي وطباعة الملصقات (Scanner & Barcode Printing)
- واجهة مخصصة للأجهزة المحمولة والماسح الضوئي (Handheld Barcode Scanner) للبحث السريع عن الأصناف.
- توليد وطباعة ملصقات الباركود والـ QR Code للأصناف بشكل مباشر.

### 7. التقارير والتقييم والـ API
- حساب تقييم المخزون (Inventory Valuation) وتكلفة الاحتفاظ بالبضاعة (Holding Costs).
- تصدير التقارير بصيغة **Excel** و **PDF**.
- **RESTful API** جاهز للتكامل مع أنظمة ERP المحاسبية وتطبيقات الجوال الخارجية.

---

## نظام الأدوار والصلاحيات (RBAC)

يحتوي النظام على 5 مستويات من الصلاحيات لضمان أمان البيانات:

| الدور (Role) | الصلاحيات والمهام |
| :--- | :--- |
| **مدير النظام (Admin)** | التحكم الكامل، إدارة الأقسام، المشرفين، واستعراض سجلات النظام (System Logs). |
| **مدير المستودعات (Warehouse Manager)** | إدارة المستودعات، الموردين، العملاء، والتحويلات المخزنية. |
| **مشرف المخزون (Supervisor)** | إضافة الأصناف، إدارة العمال، إنشاء تقارير التقييم والجرد وتصدير البيانات. |
| **أمين المخزن / العامل (Worker)** | تسجيل الحركات المخزنية، استخدام الماسح الضوئي، وطباعة باركود المنتجات. |
| **مدقق الجرد (Auditor)** | استعراض التقرير ومتابعة عمليات الجرد وتسوية الفروقات. |

---

## التقنيات المستخدمة

- **الخلفية (Backend):** PHP 8.3+ / Laravel 13.x
- **الواجهة الأمامية (Frontend):** Blade Templates / Custom CSS / JavaScript (ES6) / Vite
- **قاعدة البيانات (Database):** MySQL / PostgreSQL / SQLite
- **أدوات التطوير والجودة:** Laravel Boost, Laravel Pint, PHPUnit

---

## طريقة التثبيت والتشغيل المحلي

### 1. المتطلبات الأساسية
- **PHP** >= 8.3
- **Composer** >= 2.x
- **Node.js** >= 18.x & NPM

### 2. خطوات التثبيت

```bash
# 1. استنساخ المستودع
git clone https://github.com/USERNAME/Warehouse_management.git
cd Warehouse_management

# 2. تثبيت اعتمادات PHP و Node
composer install
npm install

# 3. إنشاء ملف البيئة وتوليد المفتاح
cp .env.example .env
php artisan key:generate

# 4. تهيئة قاعدة البيانات وتشغيل التهجيرات والبيانات الأولية
php artisan migrate --seed

# 5. تجميع ملفات الواجهة الأمامية
npm run build
```

### 3. تشغيل خادم التطوير

يمكنك تشغيل الخادم المحلي وأمر تطوير الموارد بأمر واحد:

```bash
composer run dev
```
أو عبر Artisan مباشرة:
```bash
php artisan serve
```

افتح المتصفح وزر الرابط: `http://localhost:8000`

---

## بيانات الدخول الافتراضية للتجربة (Seeders)

عند تشغيل `php artisan db:seed` يتم إنشاء الحسابات التوضيحية التالية (كلمة المرور لجميع الحسابات هي: `password`):

- **مدير النظام (Admin):** `admin@warehouse.local`
- **مشرف المخزون (Supervisor):** `supervisor@warehouse.local`
- **أمين المستودع (Worker):** `worker@warehouse.local`

---

## واجهة برمجة التطبيقات (REST API)

يوفر النظام نقاط نهاية (Endpoints) للتكامل الخارجي عبر المسار `/api/v1/`:

- `GET /api/v1/stock-balances` - الاستعلام عن أرصدة المخزون الحالية.
- `GET /api/v1/items/barcode/{code}` - البحث عن صنف بواسطة الباركود.
- `GET /api/v1/valuation-summary` - ملخص تقييم القيمة المالية للمخزون.
- `POST /api/v1/transactions/external-sync` - مزامنة الحركات من أنظمة خارجية.

---

## الاختبارات والجودة (Testing & Linting)

لتشغيل اختبارات الوحدة واختبارات الميزات (Unit & Feature Tests):

```bash
php artisan test
```

لتنسيق الكود وفق معايير Laravel Pint:

```bash
vendor/bin/pint
```

---

## الترخيص (License)

هذا المشروع مرخص بموجب رخصة **[MIT License](LICENSE)**.
