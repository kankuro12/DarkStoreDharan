@extends('layouts.storefront', ['title' => 'Saved Addresses - ' . ($storeSettings['store_name'] ?? 'DarkStore')])

@section('content')
<div class="max-w-4xl mx-auto py-4">

    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-extrabold text-slate-950 tracking-tight">My Account</h1>
        <p class="text-xs text-slate-500">Manage your orders and saved delivery addresses</p>
    </div>

    @include('storefront.partials.account-nav')

    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-extrabold text-slate-950">Saved Addresses</h2>
        <button
            type="button"
            onclick="resetAddressForm(); document.getElementById('address-modal').showModal()"
            class="px-4 py-2 bg-slate-950 hover:bg-black text-white text-xs font-bold rounded-xl transition cursor-pointer"
        >
            + Add New Address
        </button>
    </div>

    @if($addresses->isEmpty())
        <div class="py-14 text-center space-y-3 bg-white rounded-3xl border border-slate-200 shadow-xs">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
            <p class="text-sm font-bold text-slate-800">No saved addresses yet</p>
            <p class="text-xs text-slate-500 max-w-sm mx-auto">Add a delivery address once and reuse it at checkout, or it will be saved automatically the next time you order.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($addresses as $address)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <span class="text-xs font-bold text-slate-900 block truncate">{{ $address->full_name }}</span>
                            <span class="text-[11px] text-slate-500">{{ $address->phone }}</span>
                        </div>
                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full shrink-0">{{ $address->city?->name }}</span>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed">{{ $address->formatted_address }}</p>
                    @if($address->deliveryZone)
                        <p class="text-[11px] text-slate-500">Zone: {{ $address->deliveryZone->name }}</p>
                    @endif

                    <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            onclick="editAddress({{ $address->id }})"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-800 text-[11px] font-bold transition cursor-pointer"
                        >
                            Edit
                        </button>
                        <form action="{{ route('storefront.account.addresses.destroy', $address) }}" method="POST" onsubmit="return confirm('Remove this saved address?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 rounded-lg border border-rose-200 hover:bg-rose-50 text-rose-700 text-[11px] font-bold transition cursor-pointer">
                                Delete
                            </button>
                        </form>
                    </div>

                    <!-- Hidden field payload used to populate the edit modal without a round trip -->
                    <script type="application/json" id="address-data-{{ $address->id }}">
                        {!! json_encode([
                            'id' => $address->id,
                            'city_id' => $address->city_id,
                            'delivery_zone_id' => $address->delivery_zone_id,
                            'full_name' => $address->full_name,
                            'phone' => $address->phone,
                            'area' => $address->area,
                            'street' => $address->street,
                            'landmark' => $address->landmark,
                            'delivery_notes' => $address->delivery_notes,
                        ]) !!}
                    </script>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Native HTML5 Dialog: Add / Edit Address Modal -->
    <dialog id="address-modal" class="rounded-3xl p-0 w-full max-w-lg shadow-2xl backdrop:bg-slate-950/60 border-0 m-auto">
        <div class="p-6 bg-white space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 id="address-modal-title" class="font-bold text-base text-slate-900">Add New Address</h3>
                <button type="button" onclick="document.getElementById('address-modal').close()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-900 flex items-center justify-center font-bold text-sm cursor-pointer transition">✕</button>
            </div>

            <form id="address-form" method="POST" class="space-y-3 text-xs">
                @csrf
                <input type="hidden" name="_method" id="address-form-method" value="POST">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Full Name *</label>
                        <input type="text" name="full_name" id="address-full-name" required class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:outline-none text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Mobile Phone *</label>
                        <input type="tel" name="phone" id="address-phone" required class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:outline-none text-xs">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">City *</label>
                        <select name="city_id" id="address-city" required class="w-full rounded-xl border border-slate-300 p-3 bg-white focus:border-slate-900 focus:outline-none text-xs" onchange="refreshZoneOptions()">
                            <option value="">Select city</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}">{{ $city->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Delivery Zone / Ward</label>
                        <select name="delivery_zone_id" id="address-zone" class="w-full rounded-xl border border-slate-300 p-3 bg-white focus:border-slate-900 focus:outline-none text-xs">
                            <option value="">Select city first</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Area / Chowk / Ward *</label>
                        <input type="text" name="area" id="address-area" required class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:outline-none text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Street Address / House *</label>
                        <input type="text" name="street" id="address-street" required class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:outline-none text-xs">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Nearest Landmark (Optional)</label>
                    <input type="text" name="landmark" id="address-landmark" class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:outline-none text-xs">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Delivery Instructions (Optional)</label>
                    <textarea name="delivery_notes" id="address-notes" rows="2" class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:outline-none text-xs"></textarea>
                </div>

                <button type="submit" class="w-full py-3.5 bg-slate-950 hover:bg-black text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider transition shadow-md cursor-pointer">
                    Save Address
                </button>
            </form>
        </div>
    </dialog>

</div>

<script>
    const cityZones = {!! json_encode($cities->mapWithKeys(fn ($city) => [
        $city->id => $city->deliveryZones->map(fn ($zone) => ['id' => $zone->id, 'name' => $zone->name])->values(),
    ])) !!};

    function refreshZoneOptions(selectedZoneId = null) {
        const citySelect = document.getElementById('address-city');
        const zoneSelect = document.getElementById('address-zone');
        const zones = cityZones[citySelect.value] || [];

        if (zones.length === 0) {
            zoneSelect.innerHTML = '<option value="">No zones for this city</option>';
            return;
        }

        zoneSelect.innerHTML = zones.map(z => `<option value="${z.id}">${z.name}</option>`).join('');
        if (selectedZoneId) {
            zoneSelect.value = selectedZoneId;
        }
    }

    function resetAddressForm() {
        document.getElementById('address-modal-title').innerText = 'Add New Address';
        document.getElementById('address-form').action = '{{ route('storefront.account.addresses.store') }}';
        document.getElementById('address-form-method').value = 'POST';
        document.getElementById('address-form').reset();
        document.getElementById('address-zone').innerHTML = '<option value="">Select city first</option>';
    }

    function editAddress(id) {
        const data = JSON.parse(document.getElementById('address-data-' + id).textContent);

        document.getElementById('address-modal-title').innerText = 'Edit Address';
        document.getElementById('address-form').action = '/account/addresses/' + id;
        document.getElementById('address-form-method').value = 'PUT';

        document.getElementById('address-full-name').value = data.full_name ?? '';
        document.getElementById('address-phone').value = data.phone ?? '';
        document.getElementById('address-city').value = data.city_id ?? '';
        document.getElementById('address-area').value = data.area ?? '';
        document.getElementById('address-street').value = data.street ?? '';
        document.getElementById('address-landmark').value = data.landmark ?? '';
        document.getElementById('address-notes').value = data.delivery_notes ?? '';

        refreshZoneOptions(data.delivery_zone_id);

        document.getElementById('address-modal').showModal();
    }

    document.getElementById('address-modal').addEventListener('close', resetAddressForm);
</script>
@endsection
