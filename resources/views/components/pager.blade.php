@props(['page', 'routeName', 'totalAll' => null])

@php use App\Support\Paginate; use App\Support\Thai; @endphp

<div class="pager">
    <span class="info">
        แสดง <b class="num">{{ $page['from'] }}–{{ $page['to'] }}</b>
        จาก <b class="num">{{ Thai::fmt($page['total']) }}</b> รายการ
        @if ($totalAll !== null && $page['total'] !== $totalAll)
            (กรองจาก {{ Thai::fmt($totalAll) }})
        @endif
    </span>

    <form method="GET" action="{{ route($routeName) }}" class="f-inline js-auto">
        @foreach (qs([], ['per', 'page']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <select class="sel" name="per" style="height:31px;font-size:12.5px" aria-label="จำนวนแถวต่อหน้า">
            @foreach (Paginate::SIZES as $n)
                <option value="{{ $n }}" @selected($page['per'] === $n)>{{ $n }} แถว/หน้า</option>
            @endforeach
        </select>
    </form>

    <div class="pgn">
        <a class="{{ $page['page'] === 1 ? 'off' : '' }}" href="{{ route($routeName, qs(['page' => 1])) }}">«</a>
        <a class="{{ $page['page'] === 1 ? 'off' : '' }}"
           href="{{ route($routeName, qs(['page' => max(1, $page['page'] - 1)])) }}">‹</a>

        @foreach ($page['buttons'] as $n)
            <a class="{{ $n === $page['page'] ? 'on' : '' }}" href="{{ route($routeName, qs(['page' => $n])) }}">{{ $n }}</a>
        @endforeach

        <a class="{{ $page['page'] === $page['pages'] ? 'off' : '' }}"
           href="{{ route($routeName, qs(['page' => min($page['pages'], $page['page'] + 1)])) }}">›</a>
        <a class="{{ $page['page'] === $page['pages'] ? 'off' : '' }}"
           href="{{ route($routeName, qs(['page' => $page['pages']])) }}">»</a>
    </div>
</div>
