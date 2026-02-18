<div class="mt-8 border-t border-gray-100 flex flex-col md:flex-row justify-between items-start gap-8 pt-6 pb-4">
    <!-- Batch Action Tools -->
    <div class="w-full md:max-w-[280px]">
        <div class="bg-gray-50/80 border border-gray-200 rounded p-3">
            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2 pl-1">Selected Item
                Actions</span>
            <div class="flex gap-1">
                <select
                    class="flex-1 text-[11px] font-semibold border-gray-200 rounded focus:ring-0 focus:border-gray-400 py-1.5 bg-white">
                    <option>With Selected...</option>
                    <option>Delete Lines</option>
                    <option>Mark Taxed</option>
                </select>
                <button
                    class="bg-gray-700 hover:bg-black text-white px-3 py-1.5 rounded text-[10px] font-black uppercase transition">Go</button>
            </div>
        </div>
    </div>

    <!-- Final Summary Table -->
    <div class="w-full md:max-w-xs space-y-1">
        <div class="flex justify-between items-center px-2 py-1.5 hover:bg-gray-50 rounded transition">
            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Sub Total</span>
            <span class="text-xs font-bold text-gray-900 tabular-nums">{{ $record->formatted_total }}</span>
        </div>
        <div class="flex justify-between items-center px-2 py-1.5 hover:bg-gray-50 rounded transition">
            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Applied Credit</span>
            <span class="text-xs font-bold text-red-600 tabular-nums">($0.00 USD)</span>
        </div>
        <div
            class="flex justify-between items-center px-2 py-1.5 hover:bg-gray-50 rounded transition border-b border-gray-100 mb-2 pb-3">
            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Tax Total</span>
            <span class="text-xs font-bold text-gray-900 tabular-nums">$0.00 USD</span>
        </div>

        <div
            class="bg-gray-900 border-l-4 border-blue-600 text-white p-4 rounded shadow-md flex justify-between items-center">
            <div class="flex flex-col">
                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-400">Total Due</span>
                <span class="text-[8px] text-gray-400 font-medium italic">Net Payable</span>
            </div>
            <span class="text-2xl font-black tabular-nums tracking-tighter">{{ $record->formatted_total }}</span>
        </div>
    </div>
</div>