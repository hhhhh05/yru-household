@props(['field', 'label', 'routeName', 'sort' => 'hc', 'dir' => 1, 'align' => '', 'width' => ''])

{{-- หัวตารางแบบคลิกเพื่อเรียงลำดับ --}}
<th class="srt {{ $align }} {{ $sort === $field ? 'on' : '' }}" @if ($width) style="{{ $width }}" @endif>
    <a href="{{ route($routeName, qs([
        'sort' => $field,
        'dir' => $sort === $field ? -$dir : 1,
        'page' => 1,
    ])) }}">{{ $label }}<span class="ar">{{ $dir > 0 ? '↑' : '↓' }}</span></a>
</th>
