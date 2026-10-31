param([int]$Port = 9327, [switch]$CheckPdf, [ValidateSet('yearly','accounting-analysis')][string]$PdfPage='yearly')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$fixtureDir = Join-Path ([IO.Path]::GetTempPath()) ('rsuve-responsive-ui-'+(Split-Path $projectRoot -Leaf))
$chromePath = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
$profileDir = Join-Path $fixtureDir 'browser-profile'
$browser = Start-Process -FilePath $chromePath -ArgumentList @('--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--no-default-browser-check','--allow-file-access-from-files',"--remote-debugging-port=$Port", "--user-data-dir=$profileDir", 'about:blank') -WindowStyle Hidden -PassThru
$socket = New-Object System.Net.WebSockets.ClientWebSocket
$script:commandId = 0
function Invoke-DevTools([string]$Method, [hashtable]$Parameters = @{}) {
    $script:commandId++
    $message = @{id=$script:commandId; method=$Method; params=$Parameters} | ConvertTo-Json -Depth 15 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($message)
    $timeout = New-Object Threading.CancellationTokenSource
    $timeout.CancelAfter(20000)
    $null = $socket.SendAsync([ArraySegment[byte]]::new($bytes), [Net.WebSockets.WebSocketMessageType]::Text, $true, $timeout.Token).GetAwaiter().GetResult()
    do {
        $stream = New-Object IO.MemoryStream
        do {
            $buffer = New-Object byte[] 65536
            $received = $socket.ReceiveAsync([ArraySegment[byte]]::new($buffer), $timeout.Token).GetAwaiter().GetResult()
            if ($received.MessageType -eq [Net.WebSockets.WebSocketMessageType]::Close) { throw 'Browser connection closed' }
            $stream.Write($buffer,0,$received.Count)
        } until ($received.EndOfMessage)
        $response = [Text.Encoding]::UTF8.GetString($stream.ToArray()) | ConvertFrom-Json
        $stream.Dispose()
    } until ($response.id -eq $script:commandId)
    if ($response.error) { throw ($response.error | ConvertTo-Json -Compress) }
    $timeout.Dispose()
    return $response.result
}
function Get-BrowserValue([string]$Expression) {
    $result = Invoke-DevTools 'Runtime.evaluate' @{expression=$Expression; returnByValue=$true; awaitPromise=$true}
    if ($result.exceptionDetails) { throw ($result.exceptionDetails | ConvertTo-Json -Depth 5) }
    return $result.result.value
}
try {
    $pages = $null
    for ($attempt=0;$attempt -lt 25;$attempt++) {
        try { $pages = Invoke-RestMethod "http://127.0.0.1:$Port/json"; break } catch { Start-Sleep -Milliseconds 200 }
    }
    if (!$pages) { throw 'Headless browser did not start' }
    $page = @($pages | Where-Object type -eq 'page')[0]
    $null = $socket.ConnectAsync([Uri]$page.webSocketDebuggerUrl,[Threading.CancellationToken]::None).GetAwaiter().GetResult()
    $null = Invoke-DevTools 'Page.enable'
    $checks = @(
        @{name='assistant-profile-laptop'; page='assistant-profile'; width=1366; height=768; anchor='.page-head'},
        @{name='assistant-profile-phone'; page='assistant-profile'; width=390; height=844; anchor='.page-head'},
        @{name='accounting-workspace-laptop'; page='accounting-workspace'; width=1366; height=768; anchor='.page-head'},
        @{name='accounting-entry-laptop'; page='accounting-entry'; width=1366; height=768; anchor='[data-accounting-entry]'},
        @{name='accounting-entry-phone'; page='accounting-entry'; width=390; height=844; anchor='[data-accounting-entry]'},
        @{name='accounting-analysis-phone'; page='accounting-analysis'; width=390; height=844; anchor='.page-head'},
        @{name='financial-sources-laptop'; page='financial-sources'; width=1366; height=768; anchor='.page-head'},
        @{name='financial-sources-phone'; page='financial-sources'; width=390; height=844; anchor='.page-head'},
        @{name='receipts-laptop-small'; page='receipts'; width=1280; height=720; anchor='.transaction-table'},
        @{name='receipts-laptop'; page='receipts'; width=1366; height=768; anchor='.transaction-table'},
        @{name='receipts-scaled-laptop'; page='receipts'; width=1024; height=640; anchor='.transaction-table'},
        @{name='receipts-phone'; page='receipts'; width=390; height=844; anchor='.transaction-table'},
        @{name='create-small-phone'; page='create'; width=320; height=740; anchor='#asset-entry-form'},
        @{name='create-phone'; page='create'; width=390; height=844; anchor='#asset-entry-form'},
        @{name='create-tablet'; page='create'; width=768; height=1024; anchor='#asset-entry-form'},
        @{name='create-desktop'; page='create'; width=1440; height=1000; anchor='#asset-entry-form'},
        @{name='create-wide-desktop'; page='create'; width=1920; height=1080; anchor='#asset-entry-form'},
        @{name='inventory-phone'; page='inventory'; width=390; height=844; anchor='.inventory-table'},
        @{name='dashboard-phone'; page='dashboard'; width=390; height=844; anchor='.page-head'},
        @{name='opening-phone'; page='opening'; width=390; height=844; anchor='main'},
        @{name='financial-position-phone'; page='financial-position'; width=390; height=844; anchor='main'},
        @{name='yearly-phone'; page='yearly'; width=390; height=844; anchor='.yearly-card'}
    )
    $results = @()
    $moneyInputPreserved = $false
    $entryPreviewCorrect = $false
    $navigationSearchCorrect = $false
    $profilePreviewCorrect = $false
    foreach ($check in $checks) {
        $null = Invoke-DevTools 'Emulation.setDeviceMetricsOverride' @{width=$check.width; height=$check.height; deviceScaleFactor=1; mobile=($check.width -lt 700)}
        $url = 'file:///' + ((Join-Path $fixtureDir ($check.page+'.html')) -replace '\\','/')
        $null = Invoke-DevTools 'Page.navigate' @{url=$url}
        for ($attempt=0;$attempt -lt 30;$attempt++) {
            if (Get-BrowserValue "document.readyState === 'complete' && !!document.documentElement.dataset.readingSize") { break }
            Start-Sleep -Milliseconds 100
        }
        $null = Get-BrowserValue "new Promise(resolve => setTimeout(resolve, 300))"
        $null = Get-BrowserValue "document.querySelector('[data-reading-size-button=standard]').click(); if(document.getElementById('high-contrast').checked) document.getElementById('high-contrast').click();"
        $null = Get-BrowserValue "document.querySelector('$($check.anchor)')?.scrollIntoView({block:'start',behavior:'instant'})"
        $metrics = Get-BrowserValue @'
JSON.stringify({viewport:innerWidth,documentWidth:document.documentElement.scrollWidth,columns:document.querySelector('#asset-entry-form .form-grid')?getComputedStyle(document.querySelector('#asset-entry-form .form-grid')).gridTemplateColumns:null,inputHeight:document.querySelector('#name')?.getBoundingClientRect().height,sidebarHidden:document.getElementById('sidebar')?.getAttribute('aria-hidden'),cards:document.querySelector('.inventory-table')?.dataset.cardsReady,topbarY:document.querySelector('.topbar').getBoundingClientRect().y})
'@
        $entry = $metrics | ConvertFrom-Json
        $entry | Add-Member -NotePropertyName name -NotePropertyValue $check.name
        $results += $entry
        $screenshot = Invoke-DevTools 'Page.captureScreenshot' @{format='png'; captureBeyondViewport=$false}
        [IO.File]::WriteAllBytes((Join-Path $fixtureDir ($check.name+'.png')),[Convert]::FromBase64String($screenshot.data))
        if ($check.name -eq 'create-phone') {
            $moneyInputPreserved = Get-BrowserValue "(()=>{const input=document.querySelector('[data-money-field=source_price]');if(!input)return false;input.value='2.500.000,50';input.dispatchEvent(new Event('input',{bubbles:true}));return document.querySelector('input[name=source_price]').value==='2500000.50';})()"
        }
        if ($check.name -eq 'accounting-entry-phone') {
            $entryPreviewCorrect = Get-BrowserValue "(()=>{const form=document.querySelector('[data-accounting-entry]'); const debit=form.querySelector('[name=debit_account_id]');const credit=form.querySelector('[name=credit_account_id]');debit.selectedIndex=1;credit.selectedIndex=1;const input=form.querySelector('[data-money-field=amount]');input.value='1.250,25';input.dispatchEvent(new Event('input',{bubbles:true}));return form.querySelector('input[name=amount]').value==='1250.25' && form.querySelector('[data-entry-preview]').textContent.includes('Rp 1.250,25') && form.querySelector('[data-entry-preview]').textContent.includes('Kredit');})()"
        }
        if ($check.name -eq 'receipts-laptop') {
            $navigationSearchCorrect = Get-BrowserValue "(()=>{const input=document.getElementById('navigation-search');const menu=document.getElementById('primary-menu');const groups=[...menu.querySelectorAll('details')];const before=groups.map(g=>g.open);const find=term=>{input.value=term;input.dispatchEvent(new Event('input',{bubbles:true}));};find('pemasukan');const alias=[...menu.querySelectorAll('a')].some(a=>!a.hidden && a.textContent.includes('Penerimaan'));find('kas bank');const nested=[...menu.querySelectorAll('a')].some(a=>!a.hidden && a.textContent.includes('Kas & Bank') && a.closest('details').open);find('xyz-menu-tidak-ada');const empty=[...menu.querySelectorAll('a')].every(a=>a.hidden) && document.querySelector('[data-navigation-results]').textContent.includes('tidak ditemukan');document.querySelector('[data-clear-navigation]').click();const restored=groups.every((g,i)=>g.open===before[i]) && [...menu.querySelectorAll('a')].every(a=>!a.hidden);return alias&&nested&&empty&&restored;})()"
        }
        if ($check.name -eq 'assistant-profile-phone') {
            $profilePreviewCorrect = Get-BrowserValue "(()=>{const form=document.querySelector('[data-assistant-profile-form]');const input=form.querySelector('[name=name]');input.value='Contoh Nama Baru';input.dispatchEvent(new Event('input',{bubbles:true}));return form.querySelector('[data-profile-name-preview]').textContent==='Contoh Nama Baru' && form.querySelector('.assistant-avatar-fallback').textContent==='C';})()"
        }
    }
    $null = Get-BrowserValue "document.querySelector('[data-reading-size-button=large]').click(); if(!document.getElementById('high-contrast').checked) document.getElementById('high-contrast').click();"
    $large = Get-BrowserValue "JSON.stringify({size:document.documentElement.dataset.readingSize,contrast:document.documentElement.dataset.highContrast,viewport:innerWidth,documentWidth:document.documentElement.scrollWidth})" | ConvertFrom-Json
    $null = Get-BrowserValue "document.getElementById('sidebarToggleBtn').click()"
    $navigation = Get-BrowserValue "JSON.stringify({open:document.getElementById('sidebar').classList.contains('open'),hidden:document.getElementById('sidebar').getAttribute('aria-hidden'),focus:document.activeElement.textContent.trim()})" | ConvertFrom-Json
    $lastNavigationLabel = Get-BrowserValue "const items=[...document.querySelectorAll('#sidebar a[href],#sidebar button:not(:disabled),#sidebar input:not(:disabled),#sidebar summary')].filter(x=>x.getClientRects().length && x.checkVisibility()); items.at(-1).focus(); items.at(-1).textContent.trim()"
    $null = Invoke-DevTools 'Input.dispatchKeyEvent' @{type='keyDown'; key='Tab'; code='Tab'; windowsVirtualKeyCode=9}
    $tabWrapped = Get-BrowserValue "document.activeElement.textContent.trim() === 'Tutup menu'"
    $null = Invoke-DevTools 'Input.dispatchKeyEvent' @{type='keyDown'; key='Tab'; code='Tab'; windowsVirtualKeyCode=9; modifiers=8}
    $shiftTabWrapped = (Get-BrowserValue "document.activeElement.textContent.trim()") -eq $lastNavigationLabel
    $null = Get-BrowserValue "document.querySelector('[data-close-navigation]').click()"
    $closed = Get-BrowserValue "JSON.stringify({open:document.getElementById('sidebar').classList.contains('open'),focus:document.activeElement.id})" | ConvertFrom-Json
    $readingResetCorrect = Get-BrowserValue "(()=>{document.querySelector('[data-reset-reading]').click();return document.documentElement.dataset.readingSize==='standard' && document.documentElement.dataset.highContrast==='false' && !document.getElementById('high-contrast').checked;})()"
    # Responses below are local fixtures. No assistant request reaches the real application.
    $assistantTestExpression = @'
(async()=>{
    const widget=document.querySelector('[data-system-assistant]');
    const panel=widget.querySelector('.assistant-panel');
    const shell=document.querySelector('.app-shell');
    const originalFetch=window.fetch;
    let payload=null;
    window.fetch=async(url,options)=>{
        if(String(url).includes('/asisten/tanya')){
            payload=JSON.parse(options.body);
            return new Response(JSON.stringify({answer:'Panduan contoh <img src=x onerror=alert(1)>',steps:['Buka halaman sumber.'],charts:[{title:'Grafik contoh',basis:'Data tiruan',items:[{label:'Aset',value:'100.00',formatted:'Rp 100,00'},{label:'Ekuitas',value:'-50.00',formatted:'Rp -50,00'}]}],results:[{title:'AST-768 Contoh hasil',detail:'Hasil tiruan untuk pengujian UI',url:'javascript:alert(1)'}],links:[{label:'Tautan asing',url:'https://example.invalid/'}],notice:'Tidak ada data yang diubah.'}),{status:200,headers:{'Content-Type':'application/json'}});
        }
        return originalFetch(url,options);
    };
    widget.querySelector('[data-assistant-open]').click();
    await new Promise(resolve=>setTimeout(resolve,100));
    const input=widget.querySelector('#assistant-question');
    const opened=!panel.hidden && document.activeElement===input;
    const small=innerWidth<=700;
    const inert=small?shell.inert:!shell.inert;
    input.value='cara input pemasukan';
    widget.querySelector('[data-assistant-chat]').requestSubmit();
    await new Promise(resolve=>setTimeout(resolve,100));
    const log=widget.querySelector('[data-assistant-messages]');
    const safeText=log.textContent.includes('<img src=x') && !log.querySelector('img,script') && !log.querySelector('a');
    const sent=payload?.mode==='auto' && payload?.message==='cara input pemasukan';
    const search=widget.querySelector('[data-assistant-search]');
    search.closest('details').open=true;
    search.querySelector('[name=resource]').value='assets';
    search.querySelector('[name=resource]').dispatchEvent(new Event('change',{bubbles:true}));
    search.querySelector('[name=term]').value='AST-768';search.requestSubmit();
    await new Promise(resolve=>setTimeout(resolve,100));
    const searched=payload?.mode==='search' && payload?.resource==='assets' && payload?.term==='AST-768' && search.querySelector('[data-assistant-dates]').hidden;
    const analysis=widget.querySelector('[data-assistant-analysis]');analysis.closest('details').open=true;
    analysis.querySelector('[name=resource]').value='transactions';analysis.querySelector('[name=resource]').dispatchEvent(new Event('change',{bubbles:true}));
    analysis.querySelector('[name=from]').value='2026-01-01';analysis.querySelector('[name=to]').value='2026-01-31';analysis.requestSubmit();
    await new Promise(resolve=>setTimeout(resolve,100));
    const chart=log.querySelector('.assistant-chart');
    const analysed=payload?.mode==='analysis' && payload?.resource==='transactions' && payload?.from==='2026-01-01' && chart && chart.textContent.includes('Rp -50,00') && chart.querySelector('.negative').style.width==='50%';
    let trapped=true;
    if(small){
        const close=widget.querySelector('[data-assistant-close]');
        const last=widget.querySelector('[data-assistant-chat] button[type=submit]');
        last.focus();last.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',bubbles:true,cancelable:true}));
        trapped=document.activeElement===close;
        close.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',shiftKey:true,bubbles:true,cancelable:true}));
        trapped=trapped && document.activeElement===last;
    }
    const rect=panel.getBoundingClientRect();const fits=rect.left>=0 && rect.right<=innerWidth+1 && rect.top>=0 && rect.bottom<=innerHeight+1;
    widget.querySelector('[data-assistant-clear]').click();
    const cleared=log.children.length===1 && !log.textContent.includes('AST-768');
    input.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}));
    const closed=panel.hidden && !shell.inert && document.activeElement===widget.querySelector('[data-assistant-open]');
    window.fetch=originalFetch;
    return JSON.stringify({opened,inert,safeText,sent,searched,analysed:!!analysed,trapped,fits,cleared,closed});
})()
'@
    $assistantMobile = Get-BrowserValue $assistantTestExpression | ConvertFrom-Json
    $null = Get-BrowserValue "document.querySelector('[data-assistant-open]').click()"
    $assistantImage = Invoke-DevTools 'Page.captureScreenshot' @{format='png';captureBeyondViewport=$false}
    [IO.File]::WriteAllBytes((Join-Path $fixtureDir 'assistant-phone.png'),[Convert]::FromBase64String($assistantImage.data))
    $null = Get-BrowserValue "document.querySelector('[data-assistant-close]').click()"
    $null = Invoke-DevTools 'Emulation.setDeviceMetricsOverride' @{width=1366; height=768; deviceScaleFactor=1; mobile=$false}
    $assistantDesktop = Get-BrowserValue $assistantTestExpression | ConvertFrom-Json
    $null = Get-BrowserValue "document.querySelector('[data-assistant-open]').click()"
    $assistantImage = Invoke-DevTools 'Page.captureScreenshot' @{format='png';captureBeyondViewport=$false}
    [IO.File]::WriteAllBytes((Join-Path $fixtureDir 'assistant-laptop.png'),[Convert]::FromBase64String($assistantImage.data))
    $null = Get-BrowserValue "document.querySelector('[data-assistant-close]').click()"
    $null = Invoke-DevTools 'Emulation.setDeviceMetricsOverride' @{width=390; height=844; deviceScaleFactor=1; mobile=$true}
    $output = @{screens=$results; readingMode=$large; navigationOpened=$navigation; navigationClosed=$closed; tabWrapped=$tabWrapped; shiftTabWrapped=$shiftTabWrapped; moneyInputPreserved=$moneyInputPreserved; entryPreviewCorrect=$entryPreviewCorrect; navigationSearchCorrect=$navigationSearchCorrect; readingResetCorrect=$readingResetCorrect}
    $output.assistantMobile = $assistantMobile
    $output.assistantDesktop = $assistantDesktop
    $output.profilePreviewCorrect = $profilePreviewCorrect
    if ($CheckPdf) {
        $pdfUrl = 'file:///' + ((Join-Path $fixtureDir ($PdfPage+'.html')) -replace '\\','/')
        $null = Invoke-DevTools 'Page.navigate' @{url=$pdfUrl}
        for ($attempt=0;$attempt -lt 40;$attempt++) {
            if (Get-BrowserValue "document.readyState==='complete' && !!document.getElementById('export-report-pdf') && typeof html2pdf==='function'") { break }
            Start-Sleep -Milliseconds 100
        }
        $downloadDir = Join-Path $fixtureDir 'pdf-smoke'
        $null = New-Item -ItemType Directory -Path $downloadDir -Force
        $null = Invoke-DevTools 'Browser.setDownloadBehavior' @{behavior='allow'; downloadPath=$downloadDir}
        $null = Get-BrowserValue "document.getElementById('export-report-pdf').click()"
        $pdfState = $null
        for ($attempt=0;$attempt -lt 200;$attempt++) {
            $pdfState = Get-BrowserValue "JSON.stringify({status:document.getElementById('report-pdf-status').textContent,disabled:document.getElementById('export-report-pdf').disabled})" | ConvertFrom-Json
            if (!$pdfState.disabled) { break }
            Start-Sleep -Milliseconds 250
        }
        if ($pdfState.status -ne 'PDF berhasil diunduh.') { throw ('PDF export failed: '+$pdfState.status) }
        $pdfFile = $null
        for ($attempt=0;$attempt -lt 60;$attempt++) {
            $pdfFile = Get-ChildItem $downloadDir -Filter '*.pdf' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
            if ($pdfFile -and $pdfFile.Length -gt 1000) { break }
            Start-Sleep -Milliseconds 150
        }
        if (!$pdfFile -or $pdfFile.Length -lt 1000) { throw 'Downloaded PDF is missing or empty' }
        $stream = [IO.File]::OpenRead($pdfFile.FullName)
        $header = New-Object byte[] 5
        $null = $stream.Read($header,0,5)
        $stream.Dispose()
        if ([Text.Encoding]::ASCII.GetString($header) -ne '%PDF-') { throw 'Invalid PDF signature' }
        $output.pdf = @{status=$pdfState.status; file=$pdfFile.Name; bytes=$pdfFile.Length}
    }
    $output | ConvertTo-Json -Depth 8 | Set-Content -Encoding UTF8 (Join-Path $fixtureDir 'checks.json')
    $output | ConvertTo-Json -Depth 8
    if (@($results | Where-Object { $_.documentWidth -gt $_.viewport + 2 }).Count -gt 0 -or $large.documentWidth -gt $large.viewport + 2) { throw 'A page has horizontal overflow' }
    if (!$navigation.open -or $navigation.focus -ne 'Tutup menu' -or $closed.open -or $closed.focus -ne 'sidebarToggleBtn') { throw 'Navigation accessibility check failed' }
    if (!$tabWrapped -or !$shiftTabWrapped -or $large.size -ne 'large' -or $large.contrast -ne 'true') { throw 'Reading or keyboard preference check failed' }
    if (!$moneyInputPreserved) { throw 'Money input canonical value changed' }
    if (!$entryPreviewCorrect) { throw 'Non-cash entry preview or canonical amount is incorrect' }
    if (!$navigationSearchCorrect) { throw 'Navigation search, nested results, or menu restoration failed' }
    if (!$readingResetCorrect) { throw 'Reading preference reset failed' }
    if (!$profilePreviewCorrect) { throw 'Assistant profile preview failed' }
    foreach($assistantCheck in @($assistantMobile,$assistantDesktop)){
        if(@($assistantCheck.PSObject.Properties | Where-Object {$_.Value -ne $true}).Count){throw ('Assistant UI check failed: '+($assistantCheck|ConvertTo-Json -Compress))}
    }
} finally {
    if ($socket.State -eq [Net.WebSockets.WebSocketState]::Open) {
        try { $null = Invoke-DevTools 'Browser.close' } catch { }
    }
    $socket.Dispose()
}
