<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Official paper ballot — Asesewa Government Hospital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@700&family=Source+Sans+3:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#111; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #cfc8bb; color: var(--ink); font-family: "Source Sans 3", sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; gap: 1rem; padding: .9rem 1.25rem; background: #07140f; color: #f4efe6; }
        .toolbar a, .toolbar button { color: #f4efe6; background: transparent; border: 1px solid rgba(196,163,90,.5); border-radius: 999px; padding: .45rem 1rem; cursor: pointer; text-decoration: none; font: inherit; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 7mm 8mm 6mm; background: #fff; box-shadow: 0 24px 70px -36px rgba(0,0,0,.5); display: flex; flex-direction: column; }
        .sheet + .sheet { page-break-before: always; }
        .mast { display: grid; grid-template-columns: 16mm 1fr 16mm; gap: 6px; align-items: center; text-align: center; }
        .crest { width: 15mm; height: 15mm; border: 1.5px solid #111; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: 8px; font-weight: 700; letter-spacing: .08em; }
        .org { margin: 0; font-size: 11pt; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .office { margin: 1px 0 0; font-size: 8.5pt; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .how { margin: 3mm 0 3mm; text-align: center; font-size: 8.5pt; color: #444; }
        .grid { flex: 1; display: grid; grid-template-columns: 1fr 1fr; border: 2.25px solid #111; min-height: 0; }
        .col { display: flex; flex-direction: column; min-width: 0; }
        .col + .col { border-left: 2.25px solid #111; }
        .col-title { margin: 0; padding: 3.5mm 3mm; text-align: center; font-family: "Libre Baskerville", serif; font-size: 13.5pt; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; border-bottom: 1.75px solid #111; }
        .note { margin: 0; padding: 0 3mm 2mm; text-align: center; font-size: 8pt; color: #555; border-bottom: 1px solid #111; }
        .candidates { flex: 1; display: flex; flex-direction: column; }
        .row { flex: 1; display: grid; grid-template-columns: 1.15fr .85fr; min-height: 0; border-bottom: 1.5px solid #111; }
        .row:last-child { border-bottom: 0; }
        .row-yesno { flex: 1; display: flex; flex-direction: column; min-height: 0; border-bottom: 1.5px solid #111; }
        .row-yesno:last-child { border-bottom: 0; }
        .row-yesno .photo-cell { border-right: 0; border-bottom: 1.25px solid #111; padding-bottom: 3mm; }
        .row-yesno .photo-cell img, .row-yesno .photo-cell .placeholder { max-width: 58mm; height: 58mm; }
        .yesno-stamps { flex: 1; display: grid; grid-template-columns: 1fr 1fr; min-height: 0; }
        .yesno-stamps .stamp-cell + .stamp-cell { border-left: 1.25px solid #111; }
        .yesno-stamps .stamp { max-height: 42mm; height: 78%; }
        .photo-cell { padding: 3.2mm 3mm 2.6mm; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; border-right: 1.25px solid #111; }
        .photo-cell img, .photo-cell .placeholder { width: 100%; max-width: 42mm; object-fit: cover; object-position: center 15%; border: 1.4px solid #111; background: #f4f4f4; display: block; }
        .col[data-count="1"] .photo-cell img, .col[data-count="1"] .placeholder { max-width: 58mm; height: 68mm; }
        .col[data-count="2"] .photo-cell img, .col[data-count="2"] .placeholder { height: 38mm; }
        .col[data-count="3"] .photo-cell img, .col[data-count="3"] .placeholder { height: 28mm; }
        .placeholder { display: flex; align-items: center; justify-content: center; font-weight: 700; letter-spacing: .08em; color: #666; }
        .candidate-name { margin: 2.2mm 0 0; font-family: "Libre Baskerville", serif; font-size: 11pt; font-weight: 700; line-height: 1.2; }
        .stamp-cell { display: flex; align-items: center; justify-content: center; padding: 3mm; }
        .stamp { width: 100%; height: 86%; max-height: 52mm; border: 1.25px dashed #6b6b6b; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #2b3d36; }
        .col[data-count="1"] .stamp { max-height: 68mm; }
        .stamp svg { width: 22mm; height: 22mm; opacity: .26; }
        .stamp span { margin-top: 2.5mm; font-size: 7pt; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; color: #666; }
        footer.sheet-foot { margin-top: 3mm; display: flex; justify-content: space-between; font-size: 7.5pt; letter-spacing: .14em; text-transform: uppercase; color: #666; }
        @page { size: A4 portrait; margin: 6mm; }
        @media print {
            html, body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
            .sheet + .sheet { page-break-before: always; }
        }
    </style>
</head>
<body>
    @php
        $autoPrint = $autoPrint ?? false;
        $issuedTo = $issuedTo ?? null;
        $issuedStaffId = $issuedStaffId ?? null;
        $backUrl = $backUrl ?? route('ec.ballot');
    @endphp
    <div class="toolbar no-print">
        @if ($issuedTo)
            <div>
                <strong>Printing for {{ $issuedTo }}</strong>
                <span> · {{ $issuedStaffId }} · Digital access is now closed</span>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <button type="button" onclick="window.print()">Print again</button>
                <form method="POST" action="{{ $backUrl }}">
                    @csrf
                    <button type="submit">Sheets collected</button>
                </form>
            </div>
        @else
            <a href="{{ $backUrl }}">Back to ballot setup</a>
            <button type="button" onclick="window.print()">Print {{ $sheets->count() }} A4 sheets</button>
        @endif
    </div>
    @if ($autoPrint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif

    @foreach ($sheets as $sheet)
        <article class="sheet">
            <header class="mast">
                <div class="crest">AGH</div>
                <div>
                    <p class="org">Asesewa Government Hospital</p>
                    <p class="office">Office of the Electoral Commission · Welfare Election 2026</p>
                </div>
                <div class="crest">EC</div>
            </header>
            <p class="how">Two offices on this sheet — column A and column B. Look at the photograph. Press your inked thumb once in the box beside the person you choose. Do not tick or write.</p>

            <div class="grid">
                @foreach ($sheet as $position)
                    <section class="col" data-count="{{ $position->candidates->count() }}">
                        <h1 class="col-title">{{ $position->name }}</h1>
                        @if (\App\Support\UnopposedVoting::usesYesNo($position, $election))
                            <p class="note">Unopposed — mark Yes or No</p>
                        @elseif ($position->candidates->count() === 1)
                            <p class="note">Unopposed after vetting</p>
                        @endif
                        <div class="candidates">
                            @foreach ($position->candidates as $candidate)
                                @if (\App\Support\UnopposedVoting::usesYesNo($position, $election))
                                    <div class="row-yesno">
                                        <div class="photo-cell">
                                            @if ($candidate->photoUrl())
                                                <img src="{{ $candidate->photoUrl() }}" alt="{{ $candidate->name }}">
                                            @else
                                                <div class="placeholder">{{ $candidate->initials() }}</div>
                                            @endif
                                            <p class="candidate-name">{{ $candidate->name }}</p>
                                        </div>
                                        <div class="yesno-stamps">
                                            <div class="stamp-cell">
                                                <div class="stamp">
                                                    <svg viewBox="0 0 80 80" aria-hidden="true">
                                                        <circle cx="40" cy="40" r="36" fill="none" stroke="currentColor" stroke-width="1.1"/>
                                                        <path d="M40 18c10 0 18 8.2 18 20.5 0 14-7 26-18 33.5M40 22c7.5 0 13.5 6.4 13.5 16.5 0 11.5-5.4 21.2-13.5 27.5M40 26c5 0 9 5 9 12.8 0 9-3.8 16.4-9 21.7M40 30c2.8 0 5 3.4 5 9 0 6.6-2 12-5 16M40 18c-10 0-18 8.2-18 20.5 0 14 7 26 18 33.5M40 22c-7.5 0-13.5 6.4-13.5 16.5 0 11.5 5.4 21.2 13.5 27.5M40 26c-5 0-9 5-9 12.8 0 9 3.8 16.4 9 21.7M40 30c-2.8 0-5 3.4-5 9 0 6.6 2 12 5 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                                                    </svg>
                                                    <span>Yes</span>
                                                </div>
                                            </div>
                                            <div class="stamp-cell">
                                                <div class="stamp">
                                                    <svg viewBox="0 0 80 80" aria-hidden="true">
                                                        <circle cx="40" cy="40" r="36" fill="none" stroke="currentColor" stroke-width="1.1"/>
                                                        <path d="M40 18c10 0 18 8.2 18 20.5 0 14-7 26-18 33.5M40 22c7.5 0 13.5 6.4 13.5 16.5 0 11.5-5.4 21.2-13.5 27.5M40 26c5 0 9 5 9 12.8 0 9-3.8 16.4-9 21.7M40 30c2.8 0 5 3.4 5 9 0 6.6-2 12-5 16M40 18c-10 0-18 8.2-18 20.5 0 14 7 26 18 33.5M40 22c-7.5 0-13.5 6.4-13.5 16.5 0 11.5 5.4 21.2 13.5 27.5M40 26c-5 0-9 5-9 12.8 0 9 3.8 16.4 9 21.7M40 30c-2.8 0-5 3.4-5 9 0 6.6 2 12 5 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                                                    </svg>
                                                    <span>No</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="row">
                                        <div class="photo-cell">
                                            @if ($candidate->photoUrl())
                                                <img src="{{ $candidate->photoUrl() }}" alt="{{ $candidate->name }}">
                                            @else
                                                <div class="placeholder">{{ $candidate->initials() }}</div>
                                            @endif
                                            <p class="candidate-name">{{ $candidate->name }}</p>
                                        </div>
                                        <div class="stamp-cell">
                                            <div class="stamp">
                                                <svg viewBox="0 0 80 80" aria-hidden="true">
                                                    <circle cx="40" cy="40" r="36" fill="none" stroke="currentColor" stroke-width="1.1"/>
                                                    <path d="M40 18c10 0 18 8.2 18 20.5 0 14-7 26-18 33.5M40 22c7.5 0 13.5 6.4 13.5 16.5 0 11.5-5.4 21.2-13.5 27.5M40 26c5 0 9 5 9 12.8 0 9-3.8 16.4-9 21.7M40 30c2.8 0 5 3.4 5 9 0 6.6-2 12-5 16M40 18c-10 0-18 8.2-18 20.5 0 14 7 26 18 33.5M40 22c-7.5 0-13.5 6.4-13.5 16.5 0 11.5 5.4 21.2 13.5 27.5M40 26c-5 0-9 5-9 12.8 0 9 3.8 16.4 9 21.7M40 30c-2.8 0-5 3.4-5 9 0 6.6 2 12 5 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                                                </svg>
                                                <span>Thumb mark</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <footer class="sheet-foot">
                <span>Secret paper ballot</span>
                <span>Sheet {{ $loop->iteration }} of {{ $sheets->count() }}</span>
            </footer>
        </article>
    @endforeach
</body>
</html>
