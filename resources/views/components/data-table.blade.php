@props([
    'headers' => [],
    'rows',
    'empty' => null,
])

<div class="table-card">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    {{ $row }}
                @empty
                    <tr>
                        <td colspan="{{ max(count($headers), 1) }}">
                            <x-empty-state :message="$empty ?: __('common.empty_table')" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
