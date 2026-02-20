<div class="flex items-center gap-2">
    <flux:button size="sm" variant="subtle" icon="pencil-square" wire:click="openEdit({{ $getRecord()->id }})" x-on:click="$flux.modal('variant-modal').show()">Edit</flux:button>
    
    <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $getRecord()->id }})" wire:confirm="Yakin ingin menghapus grup varian ini?">Hapus</flux:button>
</div>