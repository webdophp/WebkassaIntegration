<h2>Ошибка в очереди WebkassaFetchData</h2>

<p><strong>Ошибка:</strong> {{ $errorMessage }}</p>
@if($token)
    <div><strong>Token:</strong> {{$token}}</div>
@endif
@if(!empty($cashboxes))
    <div><strong>Cashboxes:</strong>
        <pre>{{ print_r($cashboxes, true) }}</pre>
    </div>
@endif
@if($cashbox)
    <div><strong>Cashbox:</strong> {{$cashbox}}</div>
@endif
@if($shift)
    <div><strong>Shift:</strong> {{$shift}}</div>
@endif

<pre style="white-space: pre-wrap; background: #f5f5f5; padding: 10px;">
{{ $trace }}
</pre>