@php
    use Knuckles\Scribe\Tools\WritingUtils as u;
    /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp
```bash
curl --request {{$endpoint->httpMethods[0]}} \
    {{$endpoint->httpMethods[0] == 'GET' ? '--get ' : ''}}"{!! rtrim($baseUrl, '/') !!}/{{ ltrim($endpoint->boundUri, '/') }}@if(count($endpoint->cleanQueryParameters))?{!! u::printQueryParamsAsString($endpoint->cleanQueryParameters) !!}@endif"@if(count($endpoint->headers)) \
@foreach($endpoint->headers as $header => $value)
    --header "{{$header}}: {{ addslashes($value) }}"@if(! ($loop->last) || ($loop->last && count($endpoint->bodyParameters))) \
@endif
@endforeach
@endif
@if($endpoint->hasFiles() || (isset($endpoint->headers['Content-Type']) && $endpoint->headers['Content-Type'] == 'multipart/form-data' && count($endpoint->cleanBodyParameters)))
@foreach($endpoint->cleanBodyParameters as $parameter => $value)
@foreach(u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $actualValue)
    --form "@php
                    $formValue = $actualValue;
                    if (is_object($formValue) || is_array($formValue)) {
                        $formValue = json_encode($formValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    } elseif (is_bool($formValue)) {
                        $formValue = $formValue ? 'true' : 'false';
                    } else {
                        $formValue = (string) $formValue;
                    }
                    echo "$key=" . addslashes($formValue);
                @endphp"@if(!($loop->parent->last) || count($endpoint->fileParameters))\
@endif
@endforeach
@endforeach
@foreach($endpoint->fileParameters as $parameter => $value)
@foreach(u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $file)
    --form "@php
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
                    echo "$key=@" . addslashes($filePath);
                @endphp" @if(!($loop->parent->last))\
@endif
@endforeach
@endforeach
@elseif(count($endpoint->cleanBodyParameters))
@if ($endpoint->headers['Content-Type'] == 'application/x-www-form-urlencoded')
    --data "{!! http_build_query($endpoint->cleanBodyParameters, '', '&') !!}"
@else
    --data "{!! addslashes(json_encode($endpoint->cleanBodyParameters, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !!}"
@endif
@endif

```
