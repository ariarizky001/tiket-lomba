<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'manage_users', 'display_name' => 'Kelola User', 'category' => 'user_management', 'description' => 'Bisa menambah, mengedit, menghapus user'],
            
            // Event & Sessions
            ['name' => 'view_events', 'display_name' => 'Lihat Event', 'category' => 'event', 'description' => 'Bisa melihat daftar event'],
            ['name' => 'manage_events', 'display_name' => 'Kelola Event', 'category' => 'event', 'description' => 'Bisa menambah, mengedit, menghapus event'],
            ['name' => 'view_sessions', 'display_name' => 'Lihat Sesi', 'category' => 'event', 'description' => 'Bisa melihat daftar sesi'],
            ['name' => 'manage_sessions', 'display_name' => 'Kelola Sesi', 'category' => 'event', 'description' => 'Bisa menambah, mengedit, menghapus sesi'],
            
            // Participants & Invoices
            ['name' => 'view_invoices', 'display_name' => 'Lihat Peserta & Invoice', 'category' => 'participant', 'description' => 'Bisa melihat daftar peserta dan invoice'],
            ['name' => 'create_invoices', 'display_name' => 'Tambah Peserta', 'category' => 'participant', 'description' => 'Bisa menambah peserta baru'],
            ['name' => 'edit_invoices', 'display_name' => 'Edit Peserta', 'category' => 'participant', 'description' => 'Bisa mengedit data peserta'],
            ['name' => 'delete_invoices', 'display_name' => 'Hapus Peserta', 'category' => 'participant', 'description' => 'Bisa menghapus peserta'],
            ['name' => 'change_invoice_status', 'display_name' => 'Ubah Status Invoice', 'category' => 'participant', 'description' => 'Bisa mengubah status invoice (lunas/belum lunas)'],
            
            // Reports & Recap
            ['name' => 'view_reports', 'display_name' => 'Lihat Laporan', 'category' => 'report', 'description' => 'Bisa melihat laporan event'],
            ['name' => 'view_rekap', 'display_name' => 'Lihat Rekap Peserta', 'category' => 'report', 'description' => 'Bisa melihat rekap peserta'],
            
            // Expenses
            ['name' => 'view_expenses', 'display_name' => 'Lihat Pengeluaran', 'category' => 'finance', 'description' => 'Bisa melihat daftar pengeluaran'],
            ['name' => 'manage_expenses', 'display_name' => 'Kelola Pengeluaran', 'category' => 'finance', 'description' => 'Bisa menambah, mengedit, menghapus pengeluaran'],
            
            // Spreadsheet Integration
            ['name' => 'manage_spreadsheet', 'display_name' => 'Kelola Koneksi Spreadsheet', 'category' => 'integration', 'description' => 'Bisa mengatur koneksi Google Spreadsheet'],
            ['name' => 'manage_sheet_mapping', 'display_name' => 'Kelola Mapping Sheet', 'category' => 'integration', 'description' => 'Bisa mengatur mapping session ke spreadsheet'],
            
            // Latber
            ['name' => 'view_latbers', 'display_name' => 'Lihat Latber', 'category' => 'latber', 'description' => 'Bisa melihat daftar latber'],
            ['name' => 'manage_latbers', 'display_name' => 'Kelola Latber', 'category' => 'latber', 'description' => 'Bisa menambah, mengedit, menghapus latber'],
            ['name' => 'view_latber_sessions', 'display_name' => 'Lihat Sesi Latber', 'category' => 'latber', 'description' => 'Bisa melihat daftar sesi latber'],
            ['name' => 'manage_latber_sessions', 'display_name' => 'Kelola Sesi Latber', 'category' => 'latber', 'description' => 'Bisa menambah, mengedit, menghapus sesi latber'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}
