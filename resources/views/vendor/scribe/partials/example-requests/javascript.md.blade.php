@php
    use Knuckles\Scribe\Tools\WritingUtils as u;
    /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp
```javascript
const url = new URL(
    "{!! rtrim($baseUrl, '/') !!}/{{ ltrim($endpoint->boundUri, '/') }}"
);
@if(count($endpoint->cleanQueryParameters))

const params = {!! u::printQueryParamsAsKeyValue($endpoint->cleanQueryParameters, "\"", ":", 4, "{}") !!};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));
@endif

@if(!empty($endpoint->headers))
const headers = {
@foreach($endpoint->headers as $header => $value)
    "{{$header}}": "{{$value}}",
@endforeach
@empty($endpoint->headers['Accept'])
    "Accept": "application/json",
@endempty
};
@endif

@if($endpoint->hasFiles() || (isset($endpoint->headers['Content-Type']) && $endpoint->headers['Content-Type'] == 'multipart/form-data' && count($endpoint->cleanBodyParameters)))
const body = new FormData();
@foreach($endpoint->cleanBodyParameters as $parameter => $value)
@foreach( u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $actualValue)
body.append('{!! $key !!}', @php
                    if (is_object($actualValue) || is_array($actualValue)) {
                        echo "'" . addslashes(json_encode($actualValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "'";
                    } elseif (is_bool($actualValue)) {
                        echo $actualValue ? 'true' : 'false';
                    } else {
                        echo "'" . addslashes((string) $actualValue) . "'";
                    }
                @endphp);
@endforeach
@endforeach
@foreach($endpoint->fileParameters as $parameter => $value)
@foreach( u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $file)
body.append('{!! $key !!}', document.querySelector('input[name="{!! $key !!}"]').files[0]);
@endforeach
@endforeach
@elseif(count($endpoint->cleanBodyParameters))
@if ($endpoint->headers['Content-Type'] == 'application/x-www-form-urlencoded')
let body = "{!! http_build_query($endpoint->cleanBodyParameters, '', '&') !!}";
@else
let body = {!! json_encode($endpoint->cleanBodyParameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!};
@endif
@endif

fetch(url, {
    method: "{{$endpoint->httpMethods[0]}}",
@if(count($endpoint->headers))
    headers,
@endif
@if($endpoint->hasFiles() || (isset($endpoint->headers['Content-Type']) && $endpoint->headers['Content-Type'] == 'multipart/form-data' && count($endpoint->cleanBodyParameters)))
    body,
@elseif(count($endpoint->cleanBodyParameters))
@if ($endpoint->headers['Content-Type'] == 'application/x-www-form-urlencoded')
    body,
@else
    body: JSON.stringify(body),
@endif
@endif
}).then(response => response.json());
```
