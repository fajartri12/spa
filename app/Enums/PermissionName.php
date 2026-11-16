<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Katalog permission aplikasi.
 *
 * Format: {modul}.{aksi}
 * Katalog ini menjadi satu-satunya sumber kebenaran untuk nama permission
 * sehingga seeder, policy, route, dan menu selalu konsisten.
 */
enum PermissionName: string
{
    // Dashboard
    case DashboardView = 'dashboard.view';

    // Data pengguna & role
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    // Master data
    case UnitsView = 'units.view';
    case UnitsCreate = 'units.create';
    case UnitsUpdate = 'units.update';
    case UnitsDelete = 'units.delete';

    case AcademicYearsView = 'academic-years.view';
    case AcademicYearsCreate = 'academic-years.create';
    case AcademicYearsUpdate = 'academic-years.update';
    case AcademicYearsDelete = 'academic-years.delete';

    case SchoolClassesView = 'school-classes.view';
    case SchoolClassesCreate = 'school-classes.create';
    case SchoolClassesUpdate = 'school-classes.update';
    case SchoolClassesDelete = 'school-classes.delete';

    case StudentsView = 'students.view';
    case StudentsCreate = 'students.create';
    case StudentsUpdate = 'students.update';
    case StudentsDelete = 'students.delete';
    case StudentsImport = 'students.import';
    case StudentsExport = 'students.export';
    case StudentsPromote = 'students.promote';

    // Data alumni
    case AlumniView = 'alumni.view';
    case AlumniCreate = 'alumni.create';
    case AlumniUpdate = 'alumni.update';
    case AlumniDelete = 'alumni.delete';
    case AlumniExport = 'alumni.export';

    // Data orang tua/wali
    case GuardiansView = 'guardians.view';
    case GuardiansCreate = 'guardians.create';
    case GuardiansUpdate = 'guardians.update';
    case GuardiansDelete = 'guardians.delete';
    case GuardiansExport = 'guardians.export';

    case StaffView = 'staff.view';
    case StaffCreate = 'staff.create';
    case StaffUpdate = 'staff.update';
    case StaffDelete = 'staff.delete';

    case AccountsView = 'accounts.view';
    case AccountsCreate = 'accounts.create';
    case AccountsUpdate = 'accounts.update';
    case AccountsDelete = 'accounts.delete';

    // Kas keluar
    case CashTransactionsView = 'cash-transactions.view';
    case CashTransactionsCreate = 'cash-transactions.create';
    case CashTransactionsDelete = 'cash-transactions.delete';

    case PaymentCategoriesView = 'payment-categories.view';
    case PaymentCategoriesCreate = 'payment-categories.create';
    case PaymentCategoriesUpdate = 'payment-categories.update';
    case PaymentCategoriesDelete = 'payment-categories.delete';

    case BankAccountsView = 'bank-accounts.view';
    case BankAccountsCreate = 'bank-accounts.create';
    case BankAccountsUpdate = 'bank-accounts.update';
    case BankAccountsDelete = 'bank-accounts.delete';

    // Tagihan
    case BillsView = 'bills.view';
    case BillsCreate = 'bills.create';
    case BillsUpdate = 'bills.update';
    case BillsCancel = 'bills.cancel';
    case BillsGenerate = 'bills.generate';

    // Potongan tagihan siswa
    case DiscountsView = 'discounts.view';
    case DiscountsCreate = 'discounts.create';
    case DiscountsUpdate = 'discounts.update';
    case DiscountsDelete = 'discounts.delete';

    // Pembayaran
    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsVerify = 'payments.verify';
    case PaymentsCancel = 'payments.cancel';
    case PaymentsPrint = 'payments.print';

    // Laporan
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    // Pengaturan & peralatan sistem
    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';
    case ActivityLogsView = 'activity-logs.view';
    case BackupCreate = 'backup.create';
    case BackupDelete = 'backup.delete';
    case BackupRestore = 'backup.restore';

    // PPDB
    case AdmissionsView = 'admissions.view';
    case AdmissionsCreate = 'admissions.create';
    case AdmissionsUpdate = 'admissions.update';
    case AdmissionsDelete = 'admissions.delete';
    case AdmissionsVerify = 'admissions.verify';

    case SavingsView = 'savings.view';
    case SavingsCreate = 'savings.create';
    case SavingsWithdraw = 'savings.withdraw';
    case SavingsVerify = 'savings.verify';
    case SavingsExport = 'savings.export';

    // Payment gateway (VA/QRIS dinamis)
    case PaymentGatewaysView = 'payment-gateways.view';
    case PaymentGatewaysCreate = 'payment-gateways.create';
    case PaymentGatewaysCancel = 'payment-gateways.cancel';

    // Rekonsiliasi mutasi bank
    case BankReconciliationsView = 'bank-reconciliations.view';
    case BankReconciliationsImport = 'bank-reconciliations.import';
    case BankReconciliationsMatch = 'bank-reconciliations.match';

    // Penggajian / honorarium
    case PayrollsView = 'payrolls.view';
    case PayrollsCreate = 'payrolls.create';
    case PayrollsUpdate = 'payrolls.update';
    case PayrollsApprove = 'payrolls.approve';
    case PayrollsCancel = 'payrolls.cancel';

    // Bebas tanggungan & SKL
    case ClearancesView = 'clearances.view';
    case ClearancesCheck = 'clearances.check';
    case ClearancesOverride = 'clearances.override';
    case ClearancesIssue = 'clearances.issue';

    /**
     * Grup permission berdasarkan modul (prefix sebelum titik).
     *
     * @return array<string, array<int, string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            [$module] = explode('.', $case->value, 2);
            $groups[$module][] = $case->value;
        }

        return $groups;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
