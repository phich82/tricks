@if (isset($list))
    <h1>Items List</h1>
    <ul>
        @foreach ($list as $item) 
            <li style="list-style: none;">{{ $item['id'] }} - {{ $item['name'] }}
                @foreach ($perParticipants as $row)
                    @if ($row['id'] == $item['id'])
                    <p><input type="text" value="{{ $row['total'] }}" class="text-center"></p>
                    @endif
                @endforeach
            </li>
        @endforeach
    </ul>
    <div>
        {{ $list->links() }}
    </div>
@endif
<p>
    <strong>This is line for test</strong>
</p>