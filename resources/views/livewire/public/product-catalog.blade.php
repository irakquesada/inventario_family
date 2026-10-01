<div wire:poll.30s="refreshCatalog">
    <section class="bg-stone-950 text-white"><div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:py-20"><p class="text-sm font-bold uppercase tracking-[.24em] text-orange-300">Carta y catálogo</p><h1 class="mt-3 max-w-3xl text-4xl font-black sm:text-6xl">Todo lo que tenemos para ti</h1><p class="mt-4 max-w-2xl text-lg leading-8 text-stone-300">Consulta nuestros productos por categoría. La disponibilidad final puede variar en el establecimiento.</p></div></section>
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:py-14">
        <div class="rounded-3xl border border-stone-200 bg-white p-4 shadow-sm sm:p-5"><div class="grid gap-3 md:grid-cols-[1fr_18rem_auto]"><label><span class="sr-only">Buscar productos</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por nombre, descripción o marca…" class="w-full rounded-xl border border-stone-300 px-4 py-3"></label><label><span class="sr-only">Filtrar por categoría</span><select wire:model.live="category" class="w-full rounded-xl border border-stone-300 px-4 py-3"><option value="">Todas las categorías</option>@foreach($categories as $catalogCategory)<option value="{{ $catalogCategory->slug }}">{{ $catalogCategory->parent ? $catalogCategory->parent->name.' · ' : '' }}{{ $catalogCategory->name }}</option>@endforeach</select></label>@if($search !== '' || $category !== '')<button wire:click="clearFilters" type="button" class="rounded-xl border border-stone-300 px-5 py-3 font-bold hover:bg-stone-50">Limpiar</button>@endif</div></div>
        <div wire:loading.class="opacity-50" class="mt-8 transition-opacity">
            @if($products->isEmpty())
                <div class="rounded-3xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center"><h2 class="text-2xl font-black">No encontramos productos</h2><p class="mt-2 text-stone-600">Prueba con otra búsqueda o elimina los filtros.</p><button wire:click="clearFilters" type="button" class="mt-5 rounded-full bg-stone-900 px-6 py-3 font-bold text-white">Ver todos</button></div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($products as $product)
                        @php($hasCatalogImage = $product->landing_image_path && Storage::disk('public')->exists($product->landing_image_path))
                        <article wire:key="catalog-product-{{ $product->id }}" data-product-impression="{{ $product->id }}" data-product-name="{{ $product->name }}" class="group flex h-full flex-col overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                            @if($hasCatalogImage)
                                <button type="button" data-product-image-open data-image-src="{{ Storage::disk('public')->url($product->landing_image_path) }}" data-image-alt="{{ $product->name }}" class="relative aspect-[4/3] w-full overflow-hidden bg-stone-100" aria-label="Ampliar imagen de {{ $product->name }}">
                                    <img data-catalog-image src="{{ Storage::disk('public')->url($product->landing_image_path) }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-contain transition duration-500 group-hover:scale-105">
                                    <span data-image-fallback class="absolute inset-0 hidden flex-col items-center justify-center bg-gradient-to-br from-orange-100 to-stone-100 px-5 text-center text-stone-600"><span class="text-4xl text-orange-700">◇</span><strong class="mt-2 text-sm">Imagen no disponible</strong></span>
                                    <span class="absolute bottom-3 right-3 rounded-full bg-black/70 px-3 py-1.5 text-xs font-bold text-white shadow">⌕ Ampliar</span>
                                </button>
                            @else
                                <div class="flex aspect-[4/3] w-full flex-col items-center justify-center bg-gradient-to-br from-orange-100 to-stone-100 px-5 text-center text-stone-600" role="img" aria-label="{{ $product->name }} sin imagen disponible"><span class="text-5xl text-orange-700">◇</span><strong class="mt-3">Imagen no disponible</strong><small class="mt-1 text-stone-500">{{ config('brand.name') }}</small></div>
                            @endif
                            <div class="flex flex-1 flex-col p-6"><p class="text-xs font-bold uppercase tracking-wider text-orange-700">{{ $product->category?->name ?? 'Producto' }}</p><h2 class="mt-2 text-xl font-black">{{ $product->name }}</h2>@if($product->brand)<p class="mt-1 text-sm font-medium text-stone-500">{{ $product->brand->name }}</p>@endif<p class="mt-3 line-clamp-3 text-sm leading-6 text-stone-600">{{ $product->description ?: 'Consulta sus características y disponibilidad en el establecimiento.' }}</p>@if($product->price !== null)<p class="mt-auto pt-5 text-2xl font-black">{{ number_format((float) $product->price, 2, ',', '.') }} {{ config('brand.currency') === 'EUR' ? '€' : config('brand.currency') }}</p>@endif</div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-10">{{ $products->links() }}</div>
            @endif
        </div>
    </section>

    <dialog data-product-image-dialog data-dismiss-backdrop class="m-auto h-[min(92dvh,60rem)] w-[min(94vw,80rem)] max-w-none overflow-hidden rounded-3xl bg-stone-950 p-0 text-white shadow-2xl backdrop:bg-black/80">
        <div class="flex h-full flex-col"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3 sm:px-6"><strong data-product-image-title class="w-full min-w-0 truncate sm:w-auto sm:flex-1">Vista ampliada</strong><div class="flex w-full items-center justify-between gap-2 sm:w-auto"><div class="flex items-center gap-2"><button type="button" data-image-zoom-out class="rounded-lg bg-white/10 px-3 py-2 font-bold" aria-label="Alejar imagen">−</button><button type="button" data-image-zoom-reset class="rounded-lg bg-white/10 px-3 py-2 text-sm font-bold">100%</button><button type="button" data-image-zoom-in class="rounded-lg bg-white/10 px-3 py-2 font-bold" aria-label="Acercar imagen">+</button></div><button type="button" data-product-image-close class="rounded-lg bg-white px-3 py-2 font-bold text-stone-950">✕ Cerrar</button></div></div><div class="flex min-h-0 flex-1 items-center justify-center overflow-auto p-4 sm:p-8"><img data-product-image-preview alt="" class="max-h-full max-w-full object-contain transition-transform duration-150" style="transform:scale(1)"></div></div>
    </dialog>
</div>
