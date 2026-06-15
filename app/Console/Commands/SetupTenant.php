<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupTenant extends Command
{
    protected $signature = 'tenant:setup {id=demo2}';
    protected $description = 'Setup tenant database and seed data';

    public function handle(): void
    {
        $tenant = Tenant::find($this->argument('id'));

        if (!$tenant) {
            $this->error("Tenant {$this->argument('id')} not found!");
            return;
        }

        $this->info("Running migration for tenant: {$tenant->name}");

        $tenant->run(function () {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
            $this->info(Artisan::output());
        });

        $this->info("Migration completed!");

        $tenant->run(function () {
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
                '--force' => true,
            ]);
            $this->info("Seeding completed!");
        });

        $tenant->run(function () {
            $user = \App\Models\User::firstOrCreate(
                ['email' => 'admin@demo.com'],
                [
                    'name' => 'Admin',
                    'password' => bcrypt('password'),
                    'is_active' => true,
                ]
            );
            $user->assignRole('Admin');
            $this->info("User created: admin@demo.com / password");
        });

        $this->info("Tenant setup complete!");
    }
}