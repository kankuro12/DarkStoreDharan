<x-filament-panels::page>
    <style>
        .ops-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            font-family: inherit;
        }
        .ops-toolbar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        @media (max-width: 768px) {
            .ops-toolbar { grid-template-columns: 1fr; }
        }
        .ops-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        .ops-select, .ops-input {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid #cbd5e1;
            padding: 0.65rem 0.85rem;
            font-size: 0.875rem;
            background: #ffffff;
            color: #0f172a;
            outline: none;
            transition: border-color 0.2s;
        }
        .ops-select:focus, .ops-input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }
        .ops-btn-verify {
            padding: 0.65rem 1.25rem;
            background: #f59e0b;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 0.75rem;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .ops-btn-verify:hover { background: #d97706; }
        
        /* 5-Column Kanban Board */
        .ops-board {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
            align-items: start;
        }
        @media (max-width: 1200px) {
            .ops-board { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 768px) {
            .ops-board { grid-template-columns: 1fr; }
        }
        .ops-col {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 0.875rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            min-height: 480px;
        }
        .ops-col-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .ops-col-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #1e293b;
        }
        .ops-dot {
            width: 0.65rem;
            height: 0.65rem;
            border-radius: 9999px;
            display: inline-block;
        }
        .ops-count {
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 800;
        }
        
        /* Card Styles */
        .ops-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.875rem;
            padding: 0.875rem;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
            transition: all 0.2s ease;
        }
        .ops-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        }
        .ops-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ops-order-num {
            font-family: monospace;
            font-size: 0.75rem;
            font-weight: 800;
            color: #0f172a;
        }
        .ops-time {
            font-size: 0.65rem;
            color: #64748b;
            background: #f1f5f9;
            padding: 0.15rem 0.4rem;
            border-radius: 0.35rem;
        }
        .ops-items-box {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 0.5rem;
            padding: 0.5rem;
            font-size: 0.7rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .ops-btn-action {
            width: 100%;
            padding: 0.6rem 0.5rem;
            font-weight: 800;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 0.65rem;
            border: none;
            cursor: pointer;
            color: #ffffff;
            transition: filter 0.2s;
        }
        .ops-btn-action:hover { filter: brightness(0.9); }
    </style>

    <div class="ops-container">
        <!-- Top Toolbar: Warehouse Switcher & Barcode Scanner -->
        <div class="ops-toolbar">
            <div>
                <label class="ops-label">Active Dark Store / Micro-Warehouse</label>
                <select wire:model.live="selectedWarehouseId" class="ops-select">
                    @foreach($this->warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="ops-label">Scan-To-Pick (Barcode / SKU Scanner)</label>
                <form wire:submit.prevent="scanBarcode" style="display: flex; gap: 0.5rem;">
                    <input 
                        type="text" 
                        wire:model="scannedSku" 
                        placeholder="Scan or enter SKU (e.g. COKE-PET-500ML)..." 
                        class="ops-input"
                        style="text-transform: uppercase; font-family: monospace;"
                    >
                    <button type="submit" class="ops-btn-verify">
                        Verify
                    </button>
                </form>
                @if($scanResult)
                    <p style="font-size: 0.75rem; font-weight: 700; margin-top: 0.4rem; color: {{ str_starts_with($scanResult, 'MATCH') ? '#059669' : '#e11d48' }};">
                        {{ $scanResult }}
                    </p>
                @endif
            </div>
        </div>

        <!-- 5-Column High-Speed Operations Kanban Board (§22, §31.4) -->
        <div class="ops-board">
            
            <!-- Column 1: NEW ORDERS -->
            <div class="ops-col">
                <div class="ops-col-header">
                    <div class="ops-col-title">
                        <span class="ops-dot" style="background: #3b82f6;"></span>
                        <span>1. NEW</span>
                    </div>
                    <span class="ops-count" style="background: #dbeafe; color: #1e40af;">
                        {{ count($this->orders['new']) }}
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.65rem; overflow-y: auto; max-height: 70vh;">
                    @forelse($this->orders['new'] as $order)
                        <div class="ops-card">
                            <div class="ops-card-top">
                                <span class="ops-order-num">#{{ $order->order_number }}</span>
                                <span class="ops-time">{{ $order->placed_at?->diffForHumans() }}</span>
                            </div>
                            
                            <div style="font-size: 0.75rem; color: #475569;">
                                <p style="font-weight: 700; color: #0f172a; margin-bottom: 0.15rem;">📍 {{ $order->address?->area ?? 'Local Area' }}</p>
                                <p style="font-size: 0.7rem; color: #64748b;">{{ $order->items->count() }} items • Rs {{ number_format($order->grand_total) }}</p>
                            </div>

                            <div class="ops-items-box">
                                @foreach($order->items as $item)
                                    <div style="display: flex; justify-content: space-between; font-family: monospace;">
                                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px;">{{ $item->sku }}</span>
                                        <span style="font-weight: 800; color: #0f172a;">x{{ $item->quantity }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <button 
                                wire:click="startPicking({{ $order->id }})" 
                                class="ops-btn-action"
                                style="background: #2563eb;"
                            >
                                Start Picking ➔
                            </button>
                        </div>
                    @empty
                        <p style="text-align: center; font-size: 0.75rem; color: #94a3b8; padding: 2rem 0;">No new orders waiting.</p>
                    @endforelse
                </div>
            </div>

            <!-- Column 2: PICKING -->
            <div class="ops-col">
                <div class="ops-col-header">
                    <div class="ops-col-title">
                        <span class="ops-dot" style="background: #f59e0b;"></span>
                        <span>2. PICKING</span>
                    </div>
                    <span class="ops-count" style="background: #fef3c7; color: #92400e;">
                        {{ count($this->orders['picking']) }}
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.65rem; overflow-y: auto; max-height: 70vh;">
                    @forelse($this->orders['picking'] as $order)
                        <div class="ops-card" style="border: 2px solid #f59e0b;">
                            <div class="ops-card-top">
                                <span class="ops-order-num">#{{ $order->order_number }}</span>
                                <span class="ops-time" style="background: #fef3c7; color: #b45309; font-weight: 800;">IN TROLLEY</span>
                            </div>

                            <div class="ops-items-box" style="background: #fffbeb;">
                                @foreach($order->items as $item)
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-weight: 700; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px;">{{ $item->product_name }}</span>
                                        <span style="font-weight: 800; color: #b45309;">x{{ $item->quantity }}</span>
                                    </div>
                                    <span style="font-family: monospace; font-size: 0.65rem; color: #94a3b8;">{{ $item->sku }}</span>
                                @endforeach
                            </div>

                            <button 
                                wire:click="finishPacking({{ $order->id }})" 
                                class="ops-btn-action"
                                style="background: #d97706;"
                            >
                                Finish Packing ➔
                            </button>
                        </div>
                    @empty
                        <p style="text-align: center; font-size: 0.75rem; color: #94a3b8; padding: 2rem 0;">No orders currently being picked.</p>
                    @endforelse
                </div>
            </div>

            <!-- Column 3: PACKED -->
            <div class="ops-col">
                <div class="ops-col-header">
                    <div class="ops-col-title">
                        <span class="ops-dot" style="background: #a855f7;"></span>
                        <span>3. PACKED</span>
                    </div>
                    <span class="ops-count" style="background: #f3e8ff; color: #6b21a8;">
                        {{ count($this->orders['packing']) }}
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.65rem; overflow-y: auto; max-height: 70vh;">
                    @forelse($this->orders['packing'] as $order)
                        <div class="ops-card" style="border: 1px solid #d8b4fe;">
                            <div class="ops-card-top">
                                <span class="ops-order-num">#{{ $order->order_number }}</span>
                                <span class="ops-time" style="background: #f3e8ff; color: #7e22ce; font-weight: 800;">SEALED BAG</span>
                            </div>

                            <p style="font-size: 0.75rem; color: #475569;">
                                📍 {{ $order->address?->area }} ({{ $order->items->count() }} items)
                            </p>

                            <button 
                                wire:click="markReady({{ $order->id }})" 
                                class="ops-btn-action"
                                style="background: #7e22ce;"
                            >
                                Stage at Bay ➔
                            </button>
                        </div>
                    @empty
                        <p style="text-align: center; font-size: 0.75rem; color: #94a3b8; padding: 2rem 0;">No packed bags awaiting staging.</p>
                    @endforelse
                </div>
            </div>

            <!-- Column 4: READY FOR DISPATCH -->
            <div class="ops-col">
                <div class="ops-col-header">
                    <div class="ops-col-title">
                        <span class="ops-dot" style="background: #6366f1;"></span>
                        <span>4. READY</span>
                    </div>
                    <span class="ops-count" style="background: #e0e7ff; color: #3730a3;">
                        {{ count($this->orders['ready']) }}
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.65rem; overflow-y: auto; max-height: 70vh;">
                    @forelse($this->orders['ready'] as $order)
                        <div class="ops-card" style="border: 1px solid #c7d2fe;">
                            <div class="ops-card-top">
                                <span class="ops-order-num">#{{ $order->order_number }}</span>
                                <span class="ops-time" style="background: #e0e7ff; color: #4338ca; font-weight: 800;">STAGED</span>
                            </div>

                            <p style="font-size: 0.75rem; color: #475569;">
                                📍 {{ $order->address?->area }} • {{ $order->items->count() }} items
                            </p>

                            <button 
                                wire:click="dispatchOrder({{ $order->id }})" 
                                class="ops-btn-action"
                                style="background: #4f46e5;"
                            >
                                Dispatch to Rider ➔
                            </button>
                        </div>
                    @empty
                        <p style="text-align: center; font-size: 0.75rem; color: #94a3b8; padding: 2rem 0;">No orders currently ready for dispatch.</p>
                    @endforelse
                </div>
            </div>

            <!-- Column 5: OUT FOR DELIVERY -->
            <div class="ops-col">
                <div class="ops-col-header">
                    <div class="ops-col-title">
                        <span class="ops-dot" style="background: #10b981;"></span>
                        <span>5. ON ROAD</span>
                    </div>
                    <span class="ops-count" style="background: #d1fae5; color: #065f46;">
                        {{ count($this->orders['dispatched']) }}
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.65rem; overflow-y: auto; max-height: 70vh;">
                    @forelse($this->orders['dispatched'] as $order)
                        <div class="ops-card" style="border: 1px solid #a7f3d0;">
                            <div class="ops-card-top">
                                <span class="ops-order-num">#{{ $order->order_number }}</span>
                                <span class="ops-time" style="background: #d1fae5; color: #047857; font-weight: 800;">DELIVERING</span>
                            </div>

                            <p style="font-size: 0.75rem; color: #475569;">
                                🏍 Rider: <strong style="color: #0f172a;">{{ $order->deliveryAgent?->name ?? 'Assigned Rider' }}</strong>
                            </p>
                            <p style="font-size: 0.7rem; color: #64748b;">
                                📍 {{ $order->address?->area }} • Dispatched {{ $order->dispatched_at?->diffForHumans() }}
                            </p>
                        </div>
                    @empty
                        <p style="text-align: center; font-size: 0.75rem; color: #94a3b8; padding: 2rem 0;">No riders currently on delivery.</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-filament-panels::page>
