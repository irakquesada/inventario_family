<section>
    <div><p class="text-sm font-semibold text-orange-700">Catálogo</p><h1 class="mt-1 text-3xl font-bold">Marcas</h1></div>
    @if (session('status'))<p class="mt-4 rounded-xl bg-emerald-50 p-3 text-emerald-800">{{ session('status') }}</p>@endif
    @error('delete')<p class="mt-4 rounded-xl bg-red-50 p-3 text-red-700">{{ $message }}</p>@enderror
    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-stone-100 text-stone-600"><tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Productos</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
            <tbody class="divide-y divide-stone-100">@forelse($brands as $brand)<tr><td class="px-4 py-4 font-medium">{{ $brand->name }}</td><td class="px-4 py-4">{{ $brand->products_count }}</td><td class="px-4 py-4">{{ $brand->is_active ? 'Activa' : 'Inactiva' }}</td><td class="px-4 py-4 text-right"><button wire:click="edit({{ $brand->id }})" class="font-semibold text-orange-700">Editar</button><button wire:click="delete({{ $brand->id }})" wire:confirm="¿Eliminar esta marca?" class="ml-3 font-semibold text-red-700">Eliminar</button></td></tr>@empty<tr><td colspan="4" class="px-4 py-10 text-center text-stone-500">No hay marcas.</td></tr>@endforelse</tbody>
        </table></div><div class="border-t border-stone-100 p-4">{{ $brands->links() }}</div></div>
        @can('inventory.manage')<form wire:submit="save" class="h-fit space-y-4 rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="text-xl font-bold">{{ $editingId ? 'Editar marca' : 'Nueva marca' }}</h2>
            <label class="block"><span class="mb-1 block font-medium">Nombre</span><input wire:model="name" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">@error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="mb-1 block font-medium">Descripción</span><textarea wire:model="description" rows="3" class="w-full rounded-xl border border-stone-300 px-3 py-2.5"></textarea></label>
            <label class="flex items-center gap-2"><input wire:model="isActive" type="checkbox"> Marca activa</label>
            <div class="flex gap-2"><button class="rounded-xl bg-orange-700 px-4 py-2.5 font-semibold text-white">Guardar</button>@if($editingId)<button type="button" wire:click="resetForm" class="rounded-xl border border-stone-300 px-4 py-2.5">Cancelar</button>@endif</div>
        </form>@endcan
    </div>
</section>
