param([int]$Port=9338,[string]$OutputName='Panduan-Penggunaan-Sistem-RSU-Vina-Estetica.pdf')
$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
$guideDirectory=Join-Path $projectRoot 'docs/panduan-pengguna'
$pdfPath=Join-Path (Join-Path $projectRoot 'docs') $OutputName
$profileDirectory=Join-Path ([IO.Path]::GetTempPath()) 'rsuve-user-guide-chrome'
$browser=Start-Process -FilePath 'C:/Program Files/Google/Chrome/Application/chrome.exe' -ArgumentList @('--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--allow-file-access-from-files',"--remote-debugging-port=$Port","--user-data-dir=$profileDirectory",'about:blank') -WindowStyle Hidden -PassThru
$socket=New-Object System.Net.WebSockets.ClientWebSocket
$script:commandId=0
function Invoke-GuideBrowser([string]$Method,[hashtable]$Parameters=@{}) {
    $script:commandId++
    $message=@{id=$script:commandId;method=$Method;params=$Parameters}|ConvertTo-Json -Depth 15 -Compress
    $bytes=[Text.Encoding]::UTF8.GetBytes($message)
    $timeout=New-Object Threading.CancellationTokenSource
    $timeout.CancelAfter(30000)
    $null=$socket.SendAsync([ArraySegment[byte]]::new($bytes),[Net.WebSockets.WebSocketMessageType]::Text,$true,$timeout.Token).GetAwaiter().GetResult()
    do {
        $stream=New-Object IO.MemoryStream
        do {
            $buffer=New-Object byte[] 65536
            $received=$socket.ReceiveAsync([ArraySegment[byte]]::new($buffer),$timeout.Token).GetAwaiter().GetResult()
            $stream.Write($buffer,0,$received.Count)
        } until($received.EndOfMessage)
        $response=[Text.Encoding]::UTF8.GetString($stream.ToArray())|ConvertFrom-Json
        $stream.Dispose()
    } until($response.id -eq $script:commandId)
    $timeout.Dispose()
    if($response.error){throw ($response.error|ConvertTo-Json -Compress)}
    return $response.result
}
function Get-GuideValue([string]$Expression) {
    $result=Invoke-GuideBrowser 'Runtime.evaluate' @{expression=$Expression;returnByValue=$true;awaitPromise=$true}
    if($result.exceptionDetails){throw ($result.exceptionDetails|ConvertTo-Json -Depth 5)}
    return $result.result.value
}
try {
    $pages=$null
    for($attempt=0;$attempt -lt 25;$attempt++){
        try {$pages=Invoke-RestMethod "http://127.0.0.1:$Port/json";break} catch {Start-Sleep -Milliseconds 200}
    }
    if(!$pages){throw 'Chrome did not start'}
    $page=@($pages|Where-Object type -eq 'page')[0]
    $null=$socket.ConnectAsync([Uri]$page.webSocketDebuggerUrl,[Threading.CancellationToken]::None).GetAwaiter().GetResult()
    $null=Invoke-GuideBrowser 'Page.enable'
    $null=Invoke-GuideBrowser 'Emulation.setDeviceMetricsOverride' @{width=900;height=1200;deviceScaleFactor=1;mobile=$false}
    $url='file:///'+((Join-Path $guideDirectory 'panduan.html') -replace '\\','/')
    $null=Invoke-GuideBrowser 'Page.navigate' @{url=$url}
    for($attempt=0;$attempt -lt 40;$attempt++){
        if(Get-GuideValue "document.readyState==='complete' && [...document.images].every(i=>i.complete)"){break}
        Start-Sleep -Milliseconds 100
    }
    $null=Get-GuideValue 'document.fonts.ready.then(()=>true)'
    $null=Invoke-GuideBrowser 'Emulation.setEmulatedMedia' @{media='print'}
    $layout=Get-GuideValue "JSON.stringify({pages:[...document.querySelectorAll('.sheet')].map((page,index)=>({page:index+1,bottom:page.querySelector('.content').getBoundingClientRect().bottom,footer:page.querySelector('.foot').getBoundingClientRect().top,overflow:page.scrollHeight>page.clientHeight+1})),missingImages:[...document.images].filter(i=>!i.naturalWidth).map(i=>i.src)})"|ConvertFrom-Json
    if($layout.missingImages.Count){throw ('Missing images: '+($layout.missingImages -join ', '))}
    $invalid=@($layout.pages|Where-Object {$_.overflow -or $_.bottom -gt $_.footer-10})
    if($invalid.Count){throw ('Guide content overlaps footer: '+($invalid|ConvertTo-Json -Compress))}
    foreach($number in @(@(1,3,5,9,14,15,$layout.pages.Count)|Where-Object {$_ -le $layout.pages.Count}|Select-Object -Unique)){
        $null=Get-GuideValue "document.querySelectorAll('.sheet')[$($number-1)].scrollIntoView({block:'start',behavior:'instant'})"
        $capture=Invoke-GuideBrowser 'Page.captureScreenshot' @{format='png';captureBeyondViewport=$false}
        [IO.File]::WriteAllBytes((Join-Path $profileDirectory "preview-$number.png"),[Convert]::FromBase64String($capture.data))
    }
    $pdf=Invoke-GuideBrowser 'Page.printToPDF' @{printBackground=$true;preferCSSPageSize=$true;displayHeaderFooter=$false;generateTaggedPDF=$true;generateDocumentOutline=$true}
    $pdfBytes=[Convert]::FromBase64String($pdf.data)
    if([Text.Encoding]::ASCII.GetString($pdfBytes,0,5) -ne '%PDF-'){throw 'Invalid PDF'}
    $pageCount=[regex]::Matches([Text.Encoding]::ASCII.GetString($pdfBytes),'/Type\s*/Page\b').Count
    if($pageCount -ne $layout.pages.Count){throw "Expected $($layout.pages.Count) pages; PDF contains $pageCount"}
    [IO.File]::WriteAllBytes($pdfPath,$pdfBytes)
    @{file=$pdfPath;pages=$pageCount;bytes=$pdfBytes.Length;layout='no overflow or footer overlap';previewDirectory=$profileDirectory}|ConvertTo-Json
} finally {
    if($socket.State -eq [Net.WebSockets.WebSocketState]::Open){try{$null=Invoke-GuideBrowser 'Browser.close'}catch{}}
    $socket.Dispose()
}
