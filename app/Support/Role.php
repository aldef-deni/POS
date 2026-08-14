<?php

namespace App\Support;

/**
 * The three operator roles and everything each one is allowed to do.
 *
 * Keeping the matrix in one place means a permission question is always
 * answered the same way, whether it is asked by a route middleware, a
 * controller guard clause, or a Blade template deciding to hide a button.
 */
enum Role: string
{
    case Owner = 'Owner';
    case Supervisor = 'Supervisor';
    case Kasir = 'Kasir';

    /** Human label shown in the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Supervisor => 'Supervisor',
            self::Kasir => 'Kasir',
        };
    }

    /** One-line description of the role's remit, shown on the users screen. */
    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Akses penuh: pengaturan toko, manajemen pengguna, format ID produk, semua laporan termasuk laba, void & hapus data.',
            self::Supervisor => 'Operasional harian: kelola produk, stok, pelanggan, setujui void, dan lihat seluruh laporan. Tidak bisa mengubah pengaturan toko atau pengguna.',
            self::Kasir => 'Hanya terminal kasir: melakukan transaksi, cetak struk, kelola shift sendiri. Tidak memiliki akses ke dashboard pengelola.',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Owner => 'violet',
            self::Supervisor => 'blue',
            self::Kasir => 'emerald',
        };
    }

    /**
     * Every permission this role holds.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => self::ALL_PERMISSIONS,

            self::Supervisor => [
                'dashboard.access',
                'report.view', 'report.export', 'report.profit',
                'product.view', 'product.create', 'product.update', 'product.delete',
                'category.manage',
                'stock.view', 'stock.adjust',
                'customer.manage',
                'sale.view', 'sale.void', 'sale.refund',
                'shift.view.all',
                'audit.view',
                'pos.access',
            ],

            // A cashier only ever touches the terminal.
            self::Kasir => [
                'pos.access',
            ],
        };
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** Roles allowed past the dashboard door. */
    public function canAccessDashboard(): bool
    {
        return $this->can('dashboard.access');
    }

    /** Roles allowed to authorise a void at the terminal. */
    public function canApproveVoid(): bool
    {
        return $this->can('sale.void');
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::Owner, self::Supervisor, self::Kasir];
    }

    /** @return array<string,string> value => label, for select inputs. */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }

    public const ALL_PERMISSIONS = [
        'dashboard.access',
        'report.view', 'report.export', 'report.profit',
        'product.view', 'product.create', 'product.update', 'product.delete',
        'category.manage',
        'stock.view', 'stock.adjust',
        'customer.manage',
        'sale.view', 'sale.void', 'sale.refund',
        'shift.view.all', 'shift.close.other',
        'user.manage',
        'settings.manage',
        'sku.manage',
        'audit.view',
        'pos.access',
    ];
}
