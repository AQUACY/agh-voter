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
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 5mm 6mm 4mm; background: #fff; box-shadow: 0 24px 70px -36px rgba(0,0,0,.5); display: flex; flex-direction: column; }
        .sheet + .sheet { page-break-before: always; }
        .sheet-cut-hint { margin: 0 0 2.5mm; text-align: center; font-size: 7.5pt; color: #555; }
        .grid { flex: 1; display: grid; grid-template-columns: 1fr; gap: 0; min-height: 0; position: relative; }
        .grid.two { grid-template-columns: 1fr 1fr; gap: 0; }
        .col {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            border: 2.25px solid #111;
            background: #fff;
        }
        .grid.two .col:first-child { margin-right: 2.5mm; }
        .grid.two .col:last-child { margin-left: 2.5mm; }
        .cut-gutter {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 0;
            transform: translateX(-50%);
            pointer-events: none;
            z-index: 2;
        }
        .cut-gutter::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            border-left: 1.75px dashed #111;
        }
        .cut-label {
            position: absolute;
            left: 50%;
            transform: translateX(-50%) rotate(-90deg);
            transform-origin: center;
            white-space: nowrap;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            background: #fff;
            padding: 1mm 2.5mm;
            color: #333;
            border: 1px solid #111;
        }
        .cut-label.top { top: 22mm; }
        .cut-label.bottom { bottom: 22mm; }
        .cut-scissors {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 7mm;
            height: 7mm;
            border: 1.25px solid #111;
            border-radius: 999px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            line-height: 1;
        }
        .cut-scissors.mid { top: 50%; margin-top: -3.5mm; }
        .slip-mast {
            display: grid;
            grid-template-columns: 12mm 1fr 12mm;
            gap: 2.5mm;
            align-items: center;
            text-align: center;
            padding: 2.5mm 2.5mm 0;
        }
        .crest {
            width: 11.5mm;
            height: 11.5mm;
            border: 1.5px solid #111;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .08em;
        }
        .org { margin: 0; font-size: 8.5pt; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; line-height: 1.15; }
        .office { margin: 1px 0 0; font-size: 6.5pt; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; line-height: 1.2; }
        .how {
            margin: 2mm 2.5mm 2mm;
            text-align: center;
            font-size: 7pt;
            line-height: 1.35;
            color: #444;
        }
        .col-title {
            margin: 0;
            padding: 2.8mm 2.5mm;
            text-align: center;
            font-family: "Libre Baskerville", serif;
            font-size: 12pt;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            border-top: 1.75px solid #111;
            border-bottom: 1.75px solid #111;
        }
        .note { margin: 0; padding: 0 2.5mm 1.8mm; text-align: center; font-size: 7.5pt; color: #555; border-bottom: 1px solid #111; }
        .candidates { flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .row { flex: 1; display: grid; grid-template-columns: 1.15fr .85fr; min-height: 0; border-bottom: 1.5px solid #111; }
        .row:last-child { border-bottom: 0; }
        .row-yesno { flex: 1; display: flex; flex-direction: column; min-height: 0; border-bottom: 1.5px solid #111; }
        .row-yesno:last-child { border-bottom: 0; }
        .row-yesno .photo-cell { border-right: 0; border-bottom: 1.25px solid #111; padding-bottom: 3mm; }
        .row-yesno .photo-cell img, .row-yesno .photo-cell .placeholder {
            width: 42mm;
            height: 56mm;
            max-width: 90%;
        }
        .yesno-stamps { flex: 1; display: grid; grid-template-columns: 1fr 1fr; min-height: 0; }
        .yesno-stamps .stamp-cell + .stamp-cell { border-left: 1.25px solid #111; }
        .yesno-stamps .stamp { max-height: 38mm; height: 78%; }
        .photo-cell { padding: 2.8mm 2.5mm 2.2mm; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; border-right: 1.25px solid #111; min-width: 0; }
        .photo-cell img, .photo-cell .placeholder {
            width: 32mm;
            height: 43mm;
            max-width: 100%;
            object-fit: cover;
            object-position: center 22%;
            border: 1.4px solid #111;
            background: #f4f4f4;
            display: block;
        }
        .col[data-count="1"] .photo-cell img, .col[data-count="1"] .placeholder { width: 42mm; height: 56mm; }
        .col[data-count="2"] .photo-cell img, .col[data-count="2"] .placeholder { width: 36mm; height: 48mm; }
        .col[data-count="3"] .photo-cell img, .col[data-count="3"] .placeholder { width: 30mm; height: 40mm; }
        .placeholder { display: flex; align-items: center; justify-content: center; font-weight: 700; letter-spacing: .08em; color: #666; }
        .candidate-name { margin: 2mm 0 0; font-family: "Libre Baskerville", serif; font-size: 10pt; font-weight: 700; line-height: 1.2; }
        .stamp-cell { display: flex; align-items: center; justify-content: center; padding: 2.5mm; }
        .stamp { width: 100%; height: 86%; max-height: 48mm; border: 1.25px dashed #6b6b6b; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #2b3d36; }
        .col[data-count="1"] .stamp { max-height: 60mm; }
        .stamp svg { width: 20mm; height: 20mm; opacity: .26; }
        .stamp span { margin-top: 2mm; font-size: 6.5pt; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; color: #666; }
        .slip-foot {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2mm;
            padding: 2.2mm 2.5mm;
            border-top: 1.75px solid #111;
            font-size: 6.5pt;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #666;
            background: #fff;
        }
        .slip-serial { display: flex; align-items: center; gap: 2mm; text-transform: none; letter-spacing: 0; min-width: 0; }
        .slip-serial img { width: 14mm; height: 14mm; display: block; flex-shrink: 0; }
        .slip-serial .serial-meta { line-height: 1.2; min-width: 0; }
        .slip-serial .serial-label { display: block; font-size: 5.5pt; letter-spacing: .14em; text-transform: uppercase; color: #888; }
        .slip-serial .serial-code { display: block; margin-top: .5mm; font-size: 8pt; font-weight: 700; letter-spacing: .1em; color: #111; word-break: break-all; }
        .slip-mark { flex-shrink: 0; }
        .sheet-note { margin-top: 2mm; text-align: center; font-size: 6.5pt; letter-spacing: .14em; text-transform: uppercase; color: #777; }
        @page { size: A4 portrait; margin: 5mm; }
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
        $paperSerial = $paperSerial ?? null;
        $serialDisplay = $paperSerial ? \App\Models\PaperBallotSerial::format($paperSerial) : null;
        $qrUri = $paperSerial ? \App\Support\QrSvg::dataUri(\App\Models\PaperBallotSerial::normalize($paperSerial), 160) : null;
        $backUrl = $backUrl ?? route('ec.ballot');
        $fingerprint = <<<'SVG'
<svg viewBox="0 0 80 80" aria-hidden="true">
    <circle cx="40" cy="40" r="36" fill="none" stroke="currentColor" stroke-width="1.1"/>
    <path d="M40 18c10 0 18 8.2 18 20.5 0 14-7 26-18 33.5M40 22c7.5 0 13.5 6.4 13.5 16.5 0 11.5-5.4 21.2-13.5 27.5M40 26c5 0 9 5 9 12.8 0 9-3.8 16.4-9 21.7M40 30c2.8 0 5 3.4 5 9 0 6.6-2 12-5 16M40 18c-10 0-18 8.2-18 20.5 0 14 7 26 18 33.5M40 22c-7.5 0-13.5 6.4-13.5 16.5 0 11.5 5.4 21.2 13.5 27.5M40 26c-5 0-9 5-9 12.8 0 9 3.8 16.4 9 21.7M40 30c-2.8 0-5 3.4-5 9 0 6.6 2 12 5 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
</svg>
SVG;
    @endphp
    <div class="toolbar no-print">
        @if ($issuedTo)
            <div>
                <strong>Printing for {{ $issuedTo }}</strong>
                @if ($serialDisplay)
                    <span> · Sheet serial {{ $serialDisplay }} · Digital access is now closed · Cut along the dashed line so each office keeps its heading and QR</span>
                @else
                    <span> · Digital access is now closed</span>
                @endif
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
        @php $pairCount = $sheet->count(); @endphp
        <article class="sheet">
            @if ($pairCount > 1)
                <p class="sheet-cut-hint">Two offices on this sheet. Cut along the dashed centre line so each office is a separate slip. Each slip keeps its own heading, ballot serial, and QR.</p>
            @endif

            <div class="grid {{ $pairCount > 1 ? 'two' : '' }}">
                @if ($pairCount > 1)
                    <div class="cut-gutter" aria-hidden="true">
                        <span class="cut-label top">Cut here</span>
                        <span class="cut-scissors mid">✂</span>
                        <span class="cut-label bottom">Cut here</span>
                    </div>
                @endif

                @foreach ($sheet as $position)
                    <section class="col" data-count="{{ $position->candidates->count() }}">
                        <header class="slip-mast">
                            <div class="crest">AGH</div>
                            <div>
                                <p class="org">Asesewa Government Hospital</p>
                                <p class="office">Office of the Electoral Commission · Welfare Election 2026</p>
                            </div>
                            <div class="crest">EC</div>
                        </header>
                        <p class="how">Press your inked thumb once in the box beside your choice. Do not tick or write.</p>

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
                                                    {!! $fingerprint !!}
                                                    <span>Yes</span>
                                                </div>
                                            </div>
                                            <div class="stamp-cell">
                                                <div class="stamp">
                                                    {!! $fingerprint !!}
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
                                                {!! $fingerprint !!}
                                                <span>Thumb mark</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <footer class="slip-foot">
                            <span class="slip-mark">Secret slip</span>
                            @if ($qrUri && $serialDisplay)
                                <div class="slip-serial">
                                    <img src="{{ $qrUri }}" alt="Ballot serial QR for {{ $position->name }}">
                                    <div class="serial-meta">
                                        <span class="serial-label">Ballot serial</span>
                                        <span class="serial-code">{{ $serialDisplay }}</span>
                                    </div>
                                </div>
                            @else
                                <span class="slip-mark">{{ $position->name }}</span>
                            @endif
                        </footer>
                    </section>
                @endforeach
            </div>

            <p class="sheet-note">Sheet {{ $loop->iteration }} of {{ $sheets->count() }} · Split before casting so each office goes in the ballot box separately</p>
        </article>
    @endforeach
</body>
</html>
