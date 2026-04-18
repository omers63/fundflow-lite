<?php

return [

    /*
    |--------------------------------------------------------------------------
    | تطبيق FundFlow — ترجمات الواجهة (العربية الفصحى المعاصرة)
    |--------------------------------------------------------------------------
    */

    // مجموعات التنقل
    'nav.group.membership' => 'العضويات والطلبات',
    'nav.group.finance' => 'المالية والصندوق',
    'nav.group.reports' => 'التقارير والكشوف',
    'nav.group.settings' => 'الإعدادات',
    'nav.group.system' => 'النظام والصيانة',
    'nav.group.my_finance' => 'ماليتي',
    'nav.group.loans' => 'قروضي',
    'nav.group.account' => 'حسابي',

    // الموارد (أسماء الجداول/القوائم)
    'resource.member' => 'عضو',
    'resource.members' => 'الأعضاء',
    'resource.application' => 'طلب عضوية',
    'resource.applications' => 'طلبات العضوية',
    'resource.contribution' => 'مساهمة',
    'resource.contributions' => 'المساهمات',
    'resource.loan' => 'قرض',
    'resource.loans' => 'القروض',
    'resource.installment' => 'قسط',
    'resource.installments' => 'الأقساط',
    'resource.statement' => 'كشف حساب',
    'resource.statements' => 'الكشوف الشهرية',
    'resource.role' => 'دور',
    'resource.roles' => 'الأدوار والصلاحيات',

    // الحالات
    'status.pending' => 'قيد الانتظار',
    'status.approved' => 'معتمد',
    'status.rejected' => 'مرفوض',
    'status.active' => 'نشط',
    'status.completed' => 'مكتمل',
    'status.suspended' => 'معلّق مؤقتًا',
    'status.terminated' => 'منتهي',
    'status.delinquent' => 'متعثر',
    'status.overdue' => 'متأخر عن السداد',
    'status.paid' => 'مسدد',

    // الحقول
    'field.member_number' => 'رقم العضوية',
    'field.member' => 'العضو',
    'field.amount' => 'المبلغ',
    'field.amount_requested' => 'المبلغ المطلوب (ر.س.)',
    'field.amount_approved' => 'المبلغ المعتمد (ر.س.)',
    'field.purpose' => 'الغرض من الطلب',
    'field.installments' => 'الأقساط',
    'field.installments_count' => 'عدد الأقساط',
    'field.status' => 'الحالة',
    'field.payment_method' => 'طريقة الدفع',
    'field.reference_number' => 'المرجع',
    'field.notes' => 'ملاحظات',
    'field.month' => 'الشهر',
    'field.year' => 'السنة',
    'field.paid_at' => 'تاريخ السداد',
    'field.applied_at' => 'تاريخ التقديم',
    'field.approved_at' => 'تاريخ الاعتماد',
    'field.due_date' => 'تاريخ الاستحقاق',
    'field.rejection_reason' => 'سبب الرفض',
    'field.period' => 'الفترة',
    'field.opening_balance' => 'الرصيد الافتتاحي',
    'field.closing_balance' => 'الرصيد الختامي',
    'field.total_contributions' => 'إجمالي المساهمات',
    'field.total_repayments' => 'إجمالي السداد',
    'field.name' => 'الاسم',
    'field.email' => 'البريد الإلكتروني',
    'field.phone' => 'رقم الجوال',
    'field.joined_at' => 'تاريخ الانضمام',

    // الإجراءات
    'action.approve' => 'اعتماد',
    'action.reject' => 'رفض',
    'action.approve_loan' => 'اعتماد القرض',
    'action.reject_loan' => 'رفض القرض',
    'action.apply_loan' => 'طلب قرض',
    'action.generate' => 'توليد كشوف الشهر الحالي',

    // طرق الدفع
    'payment.cash' => 'نقدًا',
    'payment.bank_transfer' => 'تحويل بنكي',
    'payment.online' => 'دفع إلكتروني',

    // لوحة المعلومات والودجات
    'widget.active_members' => 'الأعضاء النشطون',
    'widget.pending_applications' => 'طلبات بانتظار المراجعة',
    'widget.total_fund' => 'رصيد الصندوق (ر.س.)',
    'widget.active_loans' => 'القروض الجارية',
    'widget.overdue_installments' => 'أقساط متأخرة',
    'widget.delinquent_members' => 'أعضاء متعثرون',
    'widget.total_contributions' => 'إجمالي مساهماتي',
    'widget.pending_loan' => 'قرض قيد الاعتماد',
    'widget.outstanding_balance' => 'الرصيد المستحق',

    // العلامة التجارية (يُفضّل الإبقاء على الاسم اللاتيني للتعرّف)
    'brand.admin' => 'FundFlow — الإدارة',
    'brand.member' => 'FundFlow — بوابة العضو',

];
