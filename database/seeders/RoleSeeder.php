<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');
            $role->syncPermissions($this->permissionsFor($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf('Roles seeded: %d', count(RoleName::cases())));
    }

    /**
     * Matriks default role → permission.
     *
     * @return array<int, string>
     */
    private function permissionsFor(RoleName $role): array
    {
        if (in_array($role, RoleName::privileged(), true)) {
            return PermissionName::values();
        }

        $all = PermissionName::values();
        $view = array_values(array_filter(
            $all,
            static fn (string $permission): bool => str_ends_with($permission, '.view'),
        ));

        return match ($role) {
            RoleName::Treasurer => [
                ...$view,
                PermissionName::BillsCreate->value,
                PermissionName::BillsUpdate->value,
                PermissionName::BillsGenerate->value,
                PermissionName::BillsCancel->value,
                PermissionName::PaymentsCreate->value,
                PermissionName::PaymentsCancel->value,
                PermissionName::PaymentsPrint->value,
                PermissionName::SavingsCreate->value,
                PermissionName::SavingsWithdraw->value,
                PermissionName::SavingsVerify->value,
                PermissionName::SavingsExport->value,
                PermissionName::StudentsPromote->value,
                PermissionName::AlumniCreate->value,
                PermissionName::AlumniUpdate->value,
                PermissionName::AlumniExport->value,
                PermissionName::GuardiansCreate->value,
                PermissionName::GuardiansUpdate->value,
                PermissionName::GuardiansExport->value,
                PermissionName::DiscountsCreate->value,
                PermissionName::DiscountsUpdate->value,
                PermissionName::DiscountsDelete->value,
                PermissionName::CashTransactionsCreate->value,
                PermissionName::CashTransactionsDelete->value,
                PermissionName::ReportsExport->value,
                // Tagihan digital (VA/QRIS) — bendahara menerbitkan & membatalkan.
                PermissionName::PaymentGatewaysCreate->value,
                PermissionName::PaymentGatewaysCancel->value,
                // Rekonsiliasi mutasi bank.
                PermissionName::BankReconciliationsImport->value,
                PermissionName::BankReconciliationsMatch->value,
                // Penggajian / honorarium.
                PermissionName::PayrollsCreate->value,
                PermissionName::PayrollsUpdate->value,
                PermissionName::PayrollsApprove->value,
                PermissionName::PayrollsCancel->value,
                // Bebas tanggungan & SKL.
                PermissionName::ClearancesCheck->value,
                PermissionName::ClearancesOverride->value,
                PermissionName::ClearancesIssue->value,
                PermissionName::ScholarshipsCreate->value,
                PermissionName::ScholarshipsUpdate->value,
                PermissionName::ScholarshipsDelete->value,
                PermissionName::ScholarshipsAward->value,
                PermissionName::BudgetsUpdate->value,
            ],
            RoleName::PaymentOfficer => [
                PermissionName::DashboardView->value,
                PermissionName::StudentsView->value,
                PermissionName::BillsView->value,
                PermissionName::PaymentsView->value,
                PermissionName::PaymentsCreate->value,
                PermissionName::PaymentsPrint->value,
                PermissionName::SavingsCreate->value,
                PermissionName::SavingsWithdraw->value,
                // Petugas loket boleh menerbitkan VA/QRIS di depan pembayar.
                PermissionName::PaymentGatewaysView->value,
                PermissionName::PaymentGatewaysCreate->value,
            ],
            RoleName::Verifier => [
                PermissionName::DashboardView->value,
                PermissionName::StudentsView->value,
                PermissionName::BillsView->value,
                PermissionName::PaymentsView->value,
                PermissionName::PaymentsVerify->value,
                PermissionName::PaymentsCancel->value,
                // Verifikator memantau tagihan digital & menindaklanjuti
                // pembayaran yang nominalnya tidak sesuai.
                PermissionName::PaymentGatewaysView->value,
            ],
            RoleName::Headmaster => $view,
            RoleName::AdmissionOperator => [
                PermissionName::DashboardView->value,
                PermissionName::AdmissionsView->value,
                PermissionName::AdmissionsCreate->value,
                PermissionName::AdmissionsUpdate->value,
                PermissionName::AdmissionsDelete->value,
                PermissionName::AdmissionsVerify->value,
            ],
            default => [],
        };
    }
}
