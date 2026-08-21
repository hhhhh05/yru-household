<?php

namespace App\Support;

/**
 * ชุดไอคอน SVG (เส้น 24×24) — ย้ายมาจากออบเจกต์ I ในไฟล์ HTML ต้นฉบับ
 * ใช้ผ่านคอมโพเนนต์ <x-icon name="users" />
 */
class Icon
{
    public const PATHS = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'users' => '<path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="9" cy="7" r="3.4"/><path d="M22 20v-1.5a4 4 0 0 0-3-3.87"/><path d="M16.5 3.6a4 4 0 0 1 0 6.8"/>',
        'box' => '<path d="M21 8v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"/><rect x="2" y="3.5" width="20" height="4.5" rx="1.4"/><line x1="10" y1="12.5" x2="14" y2="12.5"/>',
        'link' => '<path d="M10 13.5a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.2 1.2"/><path d="M14 10.5a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.2-1.2"/>',
        'map' => '<path d="M9 3.5 3 6v14.5l6-2.5 6 2.5 6-2.5V3.5l-6 2.5z"/><line x1="9" y1="3.5" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="20.5"/>',
        'shield' => '<path d="M12 21.5s7-3.2 7-9V5.5L12 2.5 5 5.5v7c0 5.8 7 9 7 9z"/><polyline points="9 11.8 11.3 14 15.2 9.6"/>',
        'swap' => '<polyline points="16 3 21 8 16 13"/><path d="M21 8H7a4 4 0 0 0-4 4"/><polyline points="8 21 3 16 8 11"/><path d="M3 16h14a4 4 0 0 0 4-4"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'edit' => '<path d="M11 4H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-6"/><path d="M18.4 2.6a2 2 0 0 1 2.8 2.8L12 14.6l-4 1 1-4z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-.8 13a2 2 0 0 1-2 1.9H7.8a2 2 0 0 1-2-1.9L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'eye' => '<path d="M1.8 12S5.4 5.5 12 5.5 22.2 12 22.2 12 18.6 18.5 12 18.5 1.8 12 1.8 12z"/><circle cx="12" cy="12" r="3"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'chk' => '<polyline points="20 6 9 17 4 12"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.7" y2="16.7"/>',
        'warn' => '<path d="M10.3 3.6 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13.5"/><line x1="12" y1="17" x2="12" y2="17"/>',
        'info' => '<circle cx="12" cy="12" r="9.5"/><line x1="12" y1="11" x2="12" y2="16.5"/><line x1="12" y1="7.6" x2="12" y2="7.6"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'down' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7.5 10.5 12 15 16.5 10.5"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'up' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="16.5 7.5 12 3 7.5 7.5"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'cash' => '<rect x="2" y="6" width="20" height="12" rx="2.2"/><circle cx="12" cy="12" r="2.6"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/>',
        'merge' => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M9 6h4a4 4 0 0 1 4 4v4"/><path d="M9 18h4a4 4 0 0 0 4-4"/><polyline points="20 11 17 14 14 11"/>',
        'phone' => '<path d="M21.5 16.9v3a2 2 0 0 1-2.2 2 19.5 19.5 0 0 1-8.5-3 19 19 0 0 1-5.9-5.9 19.5 19.5 0 0 1-3-8.6 2 2 0 0 1 2-2.2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1l-1.3 1.3a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z"/>',
        'filter' => '<polygon points="21 4 3 4 10 12.5 10 19 14 21 14 12.5"/>',
        'sheet' => '<rect x="3" y="3" width="18" height="18" rx="2.2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9.5" y1="9" x2="9.5" y2="21"/>',
        'sun' => '<circle cx="12" cy="12" r="4.2"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M21 13.2A8.5 8.5 0 1 1 10.8 3a6.8 6.8 0 0 0 10.2 10.2z"/>',
        'menu' => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'leaf' => '<path d="M12 22c5-3 8-7 8-12a8 8 0 0 0-16 0c0 5 3 9 8 12z" opacity=".35"/><path d="M12 21c0-6 0-9 0-9"/><path d="M12 12c0-3 2-5 5-5 0 3-2 5-5 5z"/><path d="M12 15c0-3-2-5-5-5 0 3 2 5 5 5z"/>',
    ];

    /** คืนค่า path ภายใน <svg> */
    public static function path(string $name): string
    {
        return self::PATHS[$name] ?? '';
    }

    /** คืนค่า <svg> เต็มรูป (ใช้ในกรณีที่ต้องประกอบ HTML ใน PHP) */
    public static function svg(string $name, float $size = 17, float $stroke = 1.9): string
    {
        return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            .' stroke-width="'.$stroke.'" stroke-linecap="round" stroke-linejoin="round">'.self::path($name).'</svg>';
    }
}
