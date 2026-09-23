@props(['target'])

<button type="button" data-barcode-open data-barcode-target="{{ $target }}" class="rounded-xl border border-stone-300 bg-white px-3 py-3 font-semibold" title="Escanear con la cámara">▣ Escanear</button>
<dialog data-barcode-dialog class="m-auto w-[min(92vw,34rem)] rounded-2xl bg-white p-0 text-stone-900 shadow-2xl backdrop:bg-black/70">
    <div class="p-5">
        <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-bold">Escanear código</h2><p class="mt-1 text-sm text-stone-600">Apunta la cámara al código de barras del producto.</p></div><button type="button" data-barcode-close class="rounded-lg border border-stone-300 px-3 py-2 font-semibold">Cerrar</button></div>
        <video data-barcode-video class="mt-4 aspect-video w-full rounded-xl bg-black object-cover" muted playsinline></video>
        <p data-barcode-status class="mt-3 rounded-lg bg-stone-100 p-3 text-sm">La cámara se solicitará al comenzar.</p>
    </div>
</dialog>
