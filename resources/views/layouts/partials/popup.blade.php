{{-- Admin-managed announcement popup (Storefront popups). Hidden while the city picker is showing. --}}
@php($popup = $currentCity && \Illuminate\Support\Facades\Schema::hasTable('popups') ? \App\Models\Popup::forRequest(request()) : null)
@if($popup)
    <dialog id="site-popup" aria-labelledby="site-popup-title" data-key="{{ $popup->storageKey() }}" data-frequency="{{ $popup->frequency }}"
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-xl">
        <div class="relative">
            @if($popup->imageUrl())
                <img src="{{ $popup->imageUrl() }}" alt="" class="w-full max-h-56 object-cover rounded-t-2xl">
            @endif
            <button type="button" data-close aria-label="Close" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/90 text-slate-700 hover:text-slate-950 flex items-center justify-center shadow-sm cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <div class="p-6 space-y-3">
                <h2 id="site-popup-title" class="text-lg font-bold text-slate-950 pr-8">{{ $popup->title }}</h2>
                @if($popup->body)
                    <p class="text-sm text-slate-600 leading-relaxed">{!! nl2br(e($popup->body)) !!}</p>
                @endif
                <div class="flex gap-3 pt-2">
                    @if($popup->button_url && $popup->button_label)
                        <a href="{{ $popup->button_url }}" data-close class="flex-1 text-center px-5 py-2.5 rounded-xl bg-slate-950 text-white font-semibold text-sm hover:bg-black transition">{{ $popup->button_label }}</a>
                    @endif
                    <button type="button" data-close class="flex-1 px-5 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition cursor-pointer">Close</button>
                </div>
            </div>
        </div>
    </dialog>
    <script>
        (function () {
            const dialog = document.getElementById('site-popup');
            if (!dialog || typeof dialog.showModal !== 'function') return;
            const key = dialog.dataset.key;
            const store = dialog.dataset.frequency === 'once' ? 'localStorage' : (dialog.dataset.frequency === 'session' ? 'sessionStorage' : null);
            const seen = () => { try { return store && window[store].getItem(key); } catch (e) { return false; } };
            const remember = () => { try { store && window[store].setItem(key, '1'); } catch (e) {} };
            if (seen()) return;
            dialog.addEventListener('close', remember);
            dialog.addEventListener('click', (e) => { if (e.target === dialog || e.target.closest('[data-close]')) dialog.close(); });
            document.addEventListener('DOMContentLoaded', () => setTimeout(() => dialog.showModal(), 600));
        })();
    </script>
@endif
