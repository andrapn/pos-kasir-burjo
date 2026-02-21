<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Buat Pelanggan Baru
        </h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Tambahkan pelanggan baru ke database Anda
        </p>
    </div>

    <form wire:submit="create" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
            <x-flux::button
                type="button"
                variant="ghost"
                wire:click="cancel"
            >
                Batal
            </x-flux::button>

            <x-flux::button
                type="submit"
                variant="primary"
                icon="check-badge"
            >
                Buat Pelanggan
            </x-flux::button>
        </div>
    </form>
</div>
