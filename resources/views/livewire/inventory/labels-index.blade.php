<section>
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-orange-700">Inventario</p><h1 class="mt-1 text-3xl font-bold">Etiquetas</h1><p class="mt-2 text-stone-600">Selecciona productos y genera una hoja PDF lista para imprimir.</p></div>
        <div class="flex w-full gap-2 sm:w-auto"><input id="label-product-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Producto, SKU o código…" class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-white px-4 py-3 sm:w-80"><x-barcode-scanner target="label-product-search" /></div>
    </div>

    @error('selectedProductIds')<p class="mt-4 rounded-xl bg-red-50 p-3 text-red-700">Selecciona al menos un producto.</p>@enderror

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
            <div class="flex items-center justify-between gap-3 border-b border-stone-200 p-4"><p><strong>{{ count($selectedProductIds) }}</strong> seleccionados</p><div class="flex gap-3"><button wire:click="selectPage({{ Js::from($products->pluck('id')->all()) }})" class="font-semibold text-orange-700">Seleccionar página</button><button wire:click="clearSelection" class="font-semibold text-stone-600">Limpiar</button></div></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-stone-100 text-stone-600"><tr><th class="w-12 px-4 py-3">Elegir</th><th class="px-4 py-3">Producto</th><th class="px-4 py-3">SKU</th><th class="px-4 py-3">Código impreso</th><th class="px-4 py-3 text-right">Precio</th></tr></thead><tbody class="divide-y divide-stone-100">
                @forelse($products as $product)<tr><td class="px-4 py-4"><input wire:model.live="selectedProductIds" type="checkbox" value="{{ $product->id }}" class="size-5"></td><td class="px-4 py-4 font-medium">{{ $product->name }}</td><td class="px-4 py-4 font-mono">{{ $product->sku }}</td><td class="px-4 py-4 font-mono">{{ $product->barcode ?: $product->sku }}</td><td class="px-4 py-4 text-right">{{ $product->price === null ? '—' : number_format((float)$product->price, 2, ',', '.').' '.config('brand.currency') }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-12 text-center text-stone-500">No hay productos que coincidan.</td></tr>@endforelse
            </tbody></table></div><div class="border-t border-stone-100 p-4">{{ $products->links() }}</div>
        </div>

        <aside class="h-fit rounded-2xl border border-stone-200 bg-white p-5 xl:sticky xl:top-5">
            <h2 class="text-xl font-bold">Preparar impresión</h2>
            <form wire:submit="download" class="mt-5 space-y-4">
                <fieldset><legend class="mb-2 font-medium">Tamaño</legend><div class="grid grid-cols-2 gap-2"><label class="cursor-pointer"><input wire:model="columns" type="radio" value="3" class="peer sr-only"><span class="block rounded-xl border p-3 text-center peer-checked:border-orange-700 peer-checked:bg-orange-50">3 por fila<br><small>Compacta</small></span></label><label class="cursor-pointer"><input wire:model="columns" type="radio" value="2" class="peer sr-only"><span class="block rounded-xl border p-3 text-center peer-checked:border-orange-700 peer-checked:bg-orange-50">2 por fila<br><small>Grande</small></span></label></div></fieldset>
                <label class="block"><span class="mb-1 block font-medium">Copias por producto</span><input wire:model="copies" type="number" min="1" max="10" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">@error('copies')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="flex items-center gap-2"><input wire:model="showPrice" type="checkbox"> Mostrar precio</label>
                <p class="rounded-xl bg-stone-100 p-3 text-sm">Se usará el código de barras comercial. Si está vacío, la etiqueta codificará el SKU mediante Code 128.</p>
                <button class="w-full rounded-xl bg-orange-700 px-4 py-3 font-semibold text-white disabled:opacity-60" wire:loading.attr="disabled"><span wire:loading.remove>Descargar etiquetas PDF</span><span wire:loading>Generando…</span></button>
            </form>
        </aside>
    </div>
</section>
