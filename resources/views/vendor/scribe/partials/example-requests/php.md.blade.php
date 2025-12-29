@php
    use Knuckles\Scribe\Tools\WritingUtils as u;
    /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp
```php
$client = new \GuzzleHttp\Client();
$url = '{!! rtrim($baseUrl, '/') . '/' . ltrim($endpoint->boundUri, '/') !!}';
@if($endpoint->hasHeadersOrQueryOrBodyParams())
$response = $client->{{ strtolower($endpoint->httpMethods[0]) }}(
    $url,
    [
@if(!empty($endpoint->headers))
        'headers' => {!! u::printPhpValue($endpoint->headers, 8) !!},
@endif
@if(!empty($endpoint->cleanQueryParameters))
        'query' => {!! u::printQueryParamsAsKeyValue($endpoint->cleanQueryParameters, "'", " =>", 12, "[]", 8) !!},
@endif
@if($endpoint->hasFiles() || (isset($endpoint->headers['Content-Type']) && $endpoint->headers['Content-Type'] == 'multipart/form-data' && !empty($endpoint->cleanBodyParameters)))
        'multipart' => [
@foreach($endpoint->cleanBodyParameters as $parameter => $value)
@foreach(u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $actualValue)
            [
                'name' => '{!! $key !!}',
                'contents' => @php
                    if (is_object($actualValue) || is_array($actualValue)) {
                        echo json_encode($actualValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    } elseif (is_bool($actualValue)) {
                        echo $actualValue ? 'true' : 'false';
                    } else {
                        echo addslashes((string) $actualValue);
                    }
                @endphp
            ],
@endforeach
@endforeach
@foreach($endpoint->fileParameters as $parameter => $value)
@foreach(u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $file)
            [
                'name' => '{!!  $key !!}',
                'contents' => @php
                    $filePath = 'path/to/file.jpg';
                    if (is_object($file) && method_exists($file, 'path')) {
                        try {
                            $path = $file->path();
                            if (is_string($path) && $path !== '(binary)' && file_exists($path)) {
                                $filePath = $path;
                            }
                        } catch (\Exception $e) {
                            // Use default path
                        }
                    }
                    echo "fopen('" . addslashes($filePath) . "', 'r')";
                @endphp
            ],
@endforeach
@endforeach
        ],
@elseif(count($endpoint->cleanBodyParameters))
@if ($endpoint->headers['Content-Type'] == 'application/x-www-form-urlencoded')
        'form_params' => {!! u::printPhpValue($endpoint->cleanBodyParameters, 8) !!},
@else
        'json' => {!! u::printPhpValue($endpoint->cleanBodyParameters, 8) !!},
@endif
@endif
    ]
);
@else
$response = $client->{{ strtolower($endpoint->httpMethods[0]) }}($url);
@endif
$body = $response->getBody();
print_r(json_decode((string) $body));
```
