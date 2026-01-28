@forelse($items as $item)
    <tr class="hover:bg-gray-100 cursor-pointer select-item-row" data-id="{{ $item->id }}" data-name="{{ $item->name }}">
        <td class="p-2 border-t">
            <div class="flex items-center">
                <img src="{{ $item->primaryImage ? $item->primaryImage->image_url : asset('images/no-image.png') }}" alt="{{ $item->name }}" class="w-10 h-10 object-cover rounded mr-3">
                <div>
                    <div class="font-semibold">{{ $item->name }}</div>
                    <div class="text-xs text-gray-500">S/N: {{ $item->serial_number ?: 'N/A' }}</div>
                </div>
            </div>
        </td>
        <td class="p-2 border-t text-center align-middle">{{ $item->quantity }}</td>
        <td class="p-2 border-t text-center align-middle">
             <x-status-badge :status="$item->status" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="3" class="text-center p-4 text-gray-500">ไม่พบรายการอุปกรณ์ที่ตรงกับการค้นหา</td>
    </tr>
@endforelse