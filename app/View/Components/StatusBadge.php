<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatusBadge extends Component
{
    public string $text;
    public string $icon;
    public string $classes;

    /**
     * Create a new component instance.
     *
     * @param string $status The status string (e.g., 'active', 'low_stock').
     */
    public function __construct(public string $status)
    {
        // ✅ Updated: Matched all text, icons, and colors to the provided image and new logic.
        [$this->text, $this->icon, $this->classes] = match ($status) {
            'active'        => ['พร้อมใช้งาน', 'fa-check-circle', 'bg-green-100 text-green-700'],
            'on_loan'       => ['ถูกยืม/ใช้งานอยู่', 'fa-user-clock', 'bg-teal-100 text-teal-700'],
            'low_stock'     => ['สต็อกต่ำ', 'fa-exclamation-triangle', 'bg-orange-100 text-orange-700'],
            'out_of_stock'  => ['สต็อกหมด', 'fa-ban', 'bg-red-100 text-red-700'],
            'repairing'     => ['ซ่อมบำรุง', 'fa-tools', 'bg-purple-100 text-purple-700'],
            'on-order'      => ['กำลังสั่งซื้อ', 'fa-hourglass-half', 'bg-blue-100 text-blue-700'],
            
            // --- Kept other statuses for system flexibility ---
            'inactive'      => ['ไม่ใช้งาน', 'fa-minus-circle', 'bg-yellow-100 text-yellow-700'],
            'disposed'      => ['จำหน่ายออก', 'fa-times-circle', 'bg-gray-200 text-gray-600'],
            'available'     => ['พร้อมใช้งาน', 'fa-check-circle', 'bg-green-100 text-green-700'], // Legacy support

            default         => [$status, 'fa-question-circle', 'bg-gray-100 text-gray-800'],
        };
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.status-badge');
    }
}