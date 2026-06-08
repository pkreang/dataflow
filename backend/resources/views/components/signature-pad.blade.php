@props([
    'name' => 'signature_image',
    'initialValue' => '',
    'savedDataUrl' => null,
    'required' => false,
])

{{--
    Signature pad — canvas drawing → base64 data URL stored in a hidden input.
    Optional `savedDataUrl` is the user's profile signature (already base64
    or a public URL); when present, the pad starts with that value loaded so
    the user can simply submit, or click "Draw new" to replace it.

    Used by:
      - components/dynamic-field.blade.php (signature field type)
      - approvals/my-approvals.blade.php (require_signature stages)
--}}

<div class="signature-pad mt-1"
     x-data="{
        signatureData: @js($initialValue ?: ($savedDataUrl ?? '')),
        savedUrl: @js($savedDataUrl),
        mode: '{{ $initialValue ? 'kept' : ($savedDataUrl ? 'saved' : 'draw') }}',
        _inited: false,
        get activeTab() { return (this.mode === 'draw' || this.mode === 'drawn') ? 'draw' : 'saved'; },
        switchTab(tab) {
            if (tab === 'saved') { this.useSaved(); }
            else { this.clearPad(); }
        },
        useSaved() {
            if (this.savedUrl) {
                this.signatureData = this.savedUrl;
                this.mode = 'saved';
            }
        },
        clearPad() {
            const c = this.$refs.padCanvas;
            if (c) {
                const ctx = c.getContext('2d');
                c.width = c.offsetWidth;
                ctx.clearRect(0, 0, c.width, c.height);
            }
            this.signatureData = '';
            this.mode = 'draw';
        },
        startDraw($event) {
            const canvas = this.$refs.padCanvas;
            // Ensure the drawing buffer matches the on-screen size. A pad first
            // shown while its container had no layout would otherwise stay at
            // width 0 — strokes would land off-canvas and nothing is captured.
            if (!this._inited || canvas.width !== canvas.offsetWidth) {
                canvas.width = canvas.offsetWidth;
                canvas.height = canvas.offsetHeight;
                this._inited = true;
            }
            const ctx = canvas.getContext('2d');
            ctx.strokeStyle = document.documentElement.classList.contains('dark') ? '#fff' : '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            const point = (e) => {
                const r = canvas.getBoundingClientRect();
                const cx = e.clientX ?? (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
                const cy = e.clientY ?? (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
                return { x: cx - r.left, y: cy - r.top };
            };
            let drawing = true;
            const p0 = point($event);
            ctx.beginPath();
            ctx.moveTo(p0.x, p0.y);
            // Listen on window, not the canvas, so a stroke that leaves the pad
            // (or releases outside it) is still drawn and captured.
            const move = (e) => { if (! drawing) return; const p = point(e); ctx.lineTo(p.x, p.y); ctx.stroke(); if (e.cancelable) e.preventDefault(); };
            const up = () => {
                drawing = false;
                this.signatureData = canvas.toDataURL();
                this.mode = 'drawn';
                window.removeEventListener('mousemove', move);
                window.removeEventListener('mouseup', up);
                window.removeEventListener('touchmove', move);
                window.removeEventListener('touchend', up);
            };
            window.addEventListener('mousemove', move);
            window.addEventListener('mouseup', up);
            window.addEventListener('touchmove', move, { passive: false });
            window.addEventListener('touchend', up);
        },
     }"
     x-init="
        if (mode === 'saved' || mode === 'kept') {
            // Pre-loaded signature — no canvas init needed until user clicks 'Draw new'
        } else {
            $nextTick(() => {
                const c = $refs.padCanvas;
                if (c) { c.width = c.offsetWidth; c.height = c.offsetHeight; _inited = true; }
            });
        }
     ">

    {{-- Tab switcher (only when a saved signature exists) --}}
    @if($savedDataUrl ?? false)
    <div class="flex gap-0 mb-2 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden w-fit text-sm">
        <button type="button"
                class="px-3 py-1.5 transition-colors"
                :class="activeTab === 'saved'
                    ? 'bg-blue-600 text-white font-medium'
                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700'"
                @click="switchTab('saved')">
            {{ __('common.signature_pad_use_saved') }}
        </button>
        <button type="button"
                class="px-3 py-1.5 transition-colors border-l border-slate-200 dark:border-slate-700"
                :class="activeTab === 'draw'
                    ? 'bg-blue-600 text-white font-medium'
                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700'"
                @click="switchTab('draw')">
            {{ __('common.signature_pad_draw_new') }}
        </button>
    </div>
    @endif

    {{-- Pre-loaded signature preview (saved profile signature OR retained value) --}}
    <template x-if="mode === 'saved' || mode === 'kept'">
        <div>
            <img :src="signatureData" alt="" class="h-20 max-w-xs object-contain border border-slate-200 dark:border-slate-700 bg-white rounded">
        </div>
    </template>

    {{-- Canvas drawing surface --}}
    <template x-if="mode === 'draw' || mode === 'drawn'">
        <div>
            <canvas
                class="w-full h-32 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 cursor-crosshair touch-none"
                x-ref="padCanvas"
                @mousedown="startDraw($event)"
                @touchstart.prevent="startDraw($event)"
            ></canvas>
            <div class="mt-1">
                <button type="button" class="text-xs text-red-500 hover:underline" @click="clearPad()">{{ __('common.signature_pad_clear') }}</button>
            </div>
        </div>
    </template>

    <input type="hidden" name="{{ $name }}" :value="signatureData" @if($required) data-required-signature="1" @endif>
</div>
