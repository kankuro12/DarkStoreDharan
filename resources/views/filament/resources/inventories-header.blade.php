<div>
    <style>
        .inv-header { margin-bottom: 1rem; }
        .inv-header .row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-bottom: 0.9rem; }
        .inv-header label { font-size: 0.85rem; font-weight: 600; color: #6b7280; }
        .dark .inv-header label { color: #9ca3af; }
        .inv-header select.warehouse { min-width: 16rem; border: 1px solid rgba(120,113,108,.35); border-radius: .5rem; padding: .45rem .7rem; font-size: .9rem; background: transparent; color: inherit; }
        .inv-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.75rem; }
        .inv-stat { border: 1px solid rgba(120,113,108,.22); border-radius: .6rem; padding: .7rem .9rem; }
        .inv-stat .label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; }
        .dark .inv-stat .label { color: #9ca3af; }
        .inv-stat .value { font-size: 1.3rem; font-weight: 700; margin-top: .15rem; }
        .inv-stat .hint { font-size: .75rem; color: #9ca3af; margin-top: .1rem; }
        .inv-stat.tone-success .value { color: #059669; }
        .inv-stat.tone-danger .value { color: #dc2626; }
        .inv-stat.tone-warning .value { color: #d97706; }
        .inv-stat.tone-info .value { color: #2563eb; }
        .inv-note { font-size: .78rem; color: #9ca3af; margin-top: .5rem; }
    </style>

    <div class="inv-header">
        <div class="row">
            <label for="inventory-warehouse">Warehouse</label>
            <select id="inventory-warehouse" class="warehouse" wire:model.live="warehouseId">
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
            <span class="inv-note">Stock actions apply to this warehouse only.</span>
        </div>

        @if (count($stats))
            <div class="inv-stats" wire:poll.30s>
                @foreach ($stats as $stat)
                    <div class="inv-stat tone-{{ $stat['tone'] }}">
                        <div class="label">{{ $stat['label'] }}</div>
                        <div class="value">{{ $stat['value'] }}</div>
                        <div class="hint">{{ $stat['hint'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
