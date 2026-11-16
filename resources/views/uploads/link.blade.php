{{--
    An upload link opened in a browser: pick files, they are staged, done.
    In the same frame as the sign-in pages, so an app's own chrome applies
    here too. The script posts each file to the link the page was opened
    on and shows the handle it got.
--}}
@php
    $text = [
        'uploading' => __('uploading…'),
        'uploaded' => __('uploaded'),
        'failed' => __('not uploaded.'),
        'retry' => __('not uploaded. Check the connection and pick it again.'),
    ];
@endphp
<x-mcp-kit::oauth.frame :title="__('Upload files')">
    <h1>{{ __('Upload files to :app', ['app' => config('app.name')]) }}</h1>
    <p class="muted">{{ __('Pick the files your assistant asked for, then go back to the chat and say they are uploaded.') }} {{ trans_choice('They are kept for :count day.|They are kept for :count days.', $ttlDays, ['count' => $ttlDays]) }}</p>

    <form method="post" action="{{ $action }}" enctype="multipart/form-data" id="upload-form">
        <input type="file" name="file" id="upload-files" multiple @if ($mimes !== []) accept="{{ implode(',', $mimes) }}" @endif style="width: 100%; margin: 8px 0 4px;">
        <noscript>
            <div class="actions"><button type="submit" class="primary">{{ __('Upload') }}</button></div>
        </noscript>
    </form>

    <ul id="upload-list" hidden></ul>

    <p class="muted">
        {{ __('Up to :size MB per file.', ['size' => number_format($maxKb / 1024, $maxKb % 1024 === 0 ? 0 : 1)]) }}
        @if ($mimes !== []) {{ __('Accepted: :types.', ['types' => implode(', ', $mimes)]) }} @endif
        @if ($expiresAt) {{ __('This link works until :time (:zone).', ['time' => $expiresAt->format('H:i'), 'zone' => $expiresAt->timezoneName]) }} @endif
        {{ __('Anyone with the link can upload in your name until then, so do not share it.') }}
    </p>

    <script>
        (function () {
            var input = document.getElementById('upload-files');
            var list = document.getElementById('upload-list');
            var action = document.getElementById('upload-form').action;
            var text = @json($text);

            function row(name) {
                var item = document.createElement('li');
                item.textContent = name + ': ' + text.uploading;
                list.hidden = false;
                list.appendChild(item);

                return item;
            }

            input.addEventListener('change', function () {
                Array.prototype.forEach.call(input.files, function (file) {
                    var item = row(file.name);
                    var body = new FormData();
                    body.append('file', file, file.name);

                    fetch(action, { method: 'POST', body: body, headers: { Accept: 'application/json' } })
                        .then(function (response) {
                            return response.json().then(function (data) { return { ok: response.ok, data: data }; });
                        })
                        .then(function (result) {
                            item.textContent = result.ok
                                ? file.name + ': ' + text.uploaded + ' (' + result.data.upload + ')'
                                : file.name + ': ' + (result.data.error || text.failed);
                        })
                        .catch(function () {
                            item.textContent = file.name + ': ' + text.retry;
                        });
                });

                input.value = '';
            });
        })();
    </script>
</x-mcp-kit::oauth.frame>
