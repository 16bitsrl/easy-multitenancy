@php($appName = $appName ?? config('app.name', 'Laravel'))
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $appName }}</title>
    <style>
        :root {
            --bg: #f4f4f5;
            --card: #ffffff;
            --border: rgba(9, 9, 11, .08);
            --ring: rgba(9, 9, 11, .12);
            --text: #18181b;
            --muted: #71717a;
            --faint: #a1a1aa;
            --accent: #18181b;
            --accent-text: #ffffff;
            --error: #dc2626;
            --hover: rgba(9, 9, 11, .04);
            --shadow: 0 1px 2px rgba(9, 9, 11, .04), 0 12px 32px -12px rgba(9, 9, 11, .12);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #09090b;
                --card: #18181b;
                --border: rgba(255, 255, 255, .08);
                --ring: rgba(255, 255, 255, .14);
                --text: #fafafa;
                --muted: #a1a1aa;
                --faint: #71717a;
                --accent: #fafafa;
                --accent-text: #18181b;
                --error: #f87171;
                --hover: rgba(255, 255, 255, .05);
                --shadow: 0 1px 2px rgba(0, 0, 0, .4), 0 16px 40px -16px rgba(0, 0, 0, .6);
            }
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }
        .card {
            width: 100%;
            max-width: 25rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            box-shadow: var(--shadow);
            padding: 2rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1.75rem;
        }
        .monogram {
            display: grid;
            place-items: center;
            width: 2.5rem;
            height: 2.5rem;
            flex-shrink: 0;
            border-radius: .625rem;
            background: var(--accent);
            color: var(--accent-text);
            font-size: 1.125rem;
            font-weight: 600;
            letter-spacing: -.02em;
        }
        .brand-name {
            margin: 0;
            font-size: 1.0625rem;
            font-weight: 600;
            letter-spacing: -.01em;
        }
        .brand-tag {
            margin: 0;
            font-size: .8125rem;
            color: var(--muted);
        }
        .lead {
            margin: 0 0 1.25rem;
            font-size: .875rem;
            color: var(--muted);
            text-wrap: pretty;
        }
        form { display: flex; flex-direction: column; gap: .625rem; }
        .field { position: relative; }
        input[type="text"] {
            width: 100%;
            font: inherit;
            font-size: 1rem;
            color: var(--text);
            background: var(--card);
            border: 1px solid var(--ring);
            border-radius: .625rem;
            padding: .625rem 6.5rem .625rem .875rem;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        input[type="text"]::placeholder { color: var(--faint); }
        input[type="text"]:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--hover);
        }
        input[type="text"].is-invalid {
            border-color: var(--error);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--error) 14%, transparent);
        }
        .error {
            margin: .5rem 0 0;
            font-size: .8125rem;
            color: var(--error);
            text-wrap: pretty;
        }
        .btn {
            position: absolute;
            top: .3125rem;
            right: .3125rem;
            bottom: .3125rem;
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            font: inherit;
            font-size: .875rem;
            font-weight: 500;
            color: var(--accent-text);
            background: var(--accent);
            border: none;
            border-radius: .4375rem;
            padding: 0 .875rem;
            cursor: pointer;
            transition: opacity .15s;
        }
        .btn:hover { opacity: .9; }
        .btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .btn svg { width: .9375rem; height: .9375rem; }
        .recent { margin-top: 1.75rem; }
        .recent-head {
            margin: 0 0 .625rem;
            font-size: .75rem;
            font-weight: 500;
            color: var(--faint);
            letter-spacing: .02em;
        }
        .recent-list {
            list-style: none;
            margin: 0;
            padding: 0;
            border-top: 1px solid var(--border);
        }
        .recent-item { border-bottom: 1px solid var(--border); }
        .recent-link {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding: .625rem .5rem;
            margin: 0 -.5rem;
            border-radius: .5rem;
            text-decoration: none;
            color: var(--text);
            transition: background-color .15s;
        }
        .recent-link:hover { background: var(--hover); }
        .recent-name { font-size: .9375rem; font-weight: 500; }
        .recent-when { font-size: .75rem; color: var(--faint); white-space: nowrap; }
        .empty {
            margin: 0;
            padding: .875rem;
            border: 1px dashed var(--border);
            border-radius: .625rem;
            font-size: .8125rem;
            color: var(--muted);
            text-align: center;
        }
        .footnote {
            margin: 1.75rem 0 0;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border);
            font-size: .75rem;
            color: var(--faint);
            text-wrap: pretty;
        }
        .footnote code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .6875rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <div class="monogram" aria-hidden="true">{{ mb_strtoupper(mb_substr($appName, 0, 1)) }}</div>
            <div>
                <h1 class="brand-name">{{ $appName }}</h1>
                <p class="brand-tag">Accesso tenant</p>
            </div>
        </div>

        <p class="lead">Inserisci l'identificativo di un tenant per entrare, oppure scegline uno tra i recenti.</p>

        <form method="POST" action="{{ url('/') }}">
            @csrf
            <div class="field">
                <input type="text" id="tenant" name="tenant" placeholder="nome-tenant" required
                       value="{{ $prefill ?? '' }}"
                       autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false"
                       inputmode="text" aria-label="Identificativo del tenant"
                       @if(! empty($error)) class="is-invalid" aria-invalid="true" aria-describedby="tenant-error" @endif
                       autofocus>
                <button type="submit" class="btn">
                    Entra
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M3 8h9M8.5 4.5 12 8l-3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            @if(! empty($error))
                <p class="error" id="tenant-error" role="alert">{{ $error }}</p>
            @endif
        </form>

        <section class="recent">
            <h2 class="recent-head">Tenant recenti</h2>
            @if(!empty($recentTenants))
                <ul class="recent-list" role="list">
                    @foreach($recentTenants as $tenant)
                        <li class="recent-item">
                            <a class="recent-link" href="{{ $tenant['url'] }}">
                                <span class="recent-name">{{ $tenant['name'] }}</span>
                                <span class="recent-when">{{ $tenant['when'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="empty">Nessun tenant visitato di recente.</p>
            @endif
        </section>

        @unless(app()->isProduction())
            <p class="footnote">
                Pagina predefinita di <code>easy-multitenancy</code>. Definisci una rotta central per <code>/</code> oppure pubblica e personalizza questa view per sostituirla.
            </p>
        @endunless
    </main>
</body>
</html>
