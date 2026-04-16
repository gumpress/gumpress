# GumPress - MIT License

param()

Write-Host

$lockFilePath = Join-Path $env:TEMP "gumpress-wp-execute.lock"
$nameFilePath = Join-Path $env:TEMP "gumpress-wp-execute.name"

if (Test-Path $nameFilePath) {
    $encodedPath = Get-Content -Path $nameFilePath -Raw
    $encodedPath = $encodedPath.Trim() # Rimuove eventuali spazi o invii accidentali
} else {
    Write-Error "Errore: File $nameFilePath non trovato."
    exit
}

$lockFile = [System.IO.File]::Open($lockFilePath, [System.IO.FileMode]::Create, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
try {
	curl.exe -x "$env:GP_PROXY_URL" -N --tls-max 1.2 --ssl-no-revoke --max-time 333 -H "X-GumPress-Auth: $env:GP_AUTH_SECRET" "$env:GP_WORDPRESS_ENDPOINT/wp-json/gumpress/execute/$encodedPath"
} 
finally {
	if ($lockFile) { 
		$lockFile.Close()
		$lockFile.Dispose() 
	}
}








