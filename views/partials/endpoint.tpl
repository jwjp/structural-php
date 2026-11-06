<tr>
    <td><code>{{ $endpoint['method'] }}</code></td>
    <td><code>{{ $endpoint['path'] }}</code></td>
    <td>{{ $endpoint['name'] ?? '' }}@if($readOnly && $endpoint['method'] !== 'GET') <span class="blocked">blocked</span>@endif</td>
</tr>
