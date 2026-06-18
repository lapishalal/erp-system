<x-filament-panels::page class="fi-simple-page">
    <div class="fi-simple-page-ctn flex min-h-screen flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="fi-simple-page-content w-full max-w-2xl space-y-8">
            
            <div class="text-center">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Daftar Perusahaan
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Buat akun perusahaan baru untuk mulai menggunakan ERP
                </p>
            </div>

            <div class="fi-section rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <form wire:submit="register" class="space-y-6">
                    {{ $this->form }}

                    <div class="flex items-center justify-between pt-4">
                        <x-filament::button type="submit" size="lg" color="primary">
                            Daftar Perusahaan
                        </x-filament::button>

                        <a href="{{ url('/admin/login') }}" class="text-sm font-medium text-primary-600 hover:text-primary-500 hover:underline">
                            Sudah punya akun? Login
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-filament-panels::page>