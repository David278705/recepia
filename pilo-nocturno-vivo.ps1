# ============================================================
#  Pilo - Ciclo nocturno con vista EN VIVO (estilo VS Code)
#  Uso:   powershell -ExecutionPolicy Bypass -File .\pilo-nocturno-vivo.ps1
#  Parar: New-Item STOP -ItemType File   (en la raiz del proyecto)
# ============================================================

# ---------------- CONFIGURACION ----------------
$ProyectoDir      = "C:\xampp\htdocs\vigia"
$RamaTrabajo      = "autonomo/$(Get-Date -Format 'yyyy-MM-dd')"
$MaxIteraciones   = 100
$PausaEntreIter   = 30
$EsperaLimite     = 900     # 15 min si no hay creditos
$LogDir           = Join-Path $ProyectoDir "docs\logs-nocturnos"
# ------------------------------------------------

$ErrorActionPreference = "Continue"
$OutputEncoding = [System.Text.Encoding]::UTF8
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$Prompt = @"
Vas a trabajar solo, sin supervision. NO hagas preguntas: nadie las va a responder.

Lee y aplica la skill 'trabajo-autonomo' (.claude/skills/trabajo-autonomo/) durante toda la sesion, junto con sus referencias calidad.md y contexto-pilo.md. Es tu manual de operacion.

Antes de trabajar, orientate: lee docs/WORKLOG.md (entradas recientes), docs/BACKLOG.md, docs/DECISIONES.md y docs/PENDIENTE-HUMANO.md para saber en que quedo la sesion anterior. Confirma que estas en la rama de trabajo (NO main) y que los tests pasan; si algo esta roto, arreglarlo es tu primera tarea.

Mision: integrar y refinar al maximo lo que ya existe. Consolidar, endurecer y mejorar, no construir funcionalidades nuevas grandes. Estas autorizado a tomar iniciativa en cualquier mejora real siempre que este dentro del alcance del proyecto, no rompa nada, no cruce las lineas rojas de la skill, y quede registrada y sea reversible.

Ante dudas: elige lo mas conservador y reversible, registralo en docs/DECISIONES.md y sigue. Lo que requiera criterio de negocio o del dueno va a docs/PENDIENTE-HUMANO.md y pasas a otra tarea.

Trabaja en tareas completas: una a la vez, commits atomicos, tests en verde antes de cada commit, y una entrada en docs/WORKLOG.md por cada tarea terminada. Nunca dejes el repo roto ni cambios sin commitear.

En esta sesion completa entre 1 y 3 tareas y luego termina ordenadamente: deja todo commiteado y escribe al inicio de docs/WORKLOG.md un bloque '## FIN DE ITERACION' con lo que lograste, lo que quedo pendiente y que sigue.

Empieza ahora.
"@

if (-not (Test-Path $ProyectoDir)) { Write-Host "ERROR: no existe $ProyectoDir" -ForegroundColor Red; exit 1 }
Set-Location $ProyectoDir
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

function Log($msg, $color = "Gray") {
    $ts = Get-Date -Format "HH:mm:ss"
    Write-Host "[$ts] $msg" -ForegroundColor $color
    Add-Content -Path (Join-Path $LogDir "ciclo.log") -Value "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $msg"
}

# ---- Traductor de stream-json -> consola legible (estilo VS Code) ----
# Recibe cada linea de JSON que emite Claude Code y la pinta segun su tipo.
function Render-StreamLine($linea) {
    if ([string]::IsNullOrWhiteSpace($linea)) { return }
    try { $e = $linea | ConvertFrom-Json -ErrorAction Stop } catch { return }

    switch ($e.type) {
        "assistant" {
            foreach ($b in $e.message.content) {
                switch ($b.type) {
                    "text" {
                        if ($b.text.Trim()) { Write-Host $b.text -ForegroundColor White }
                    }
                    "thinking" {
                        # El "razonamiento" que ves en VS Code
                        Write-Host ("`n  [pensando] " + $b.thinking.Trim()) -ForegroundColor DarkGray
                    }
                    "tool_use" {
                        $nombre = $b.name
                        $det = ""
                        switch ($nombre) {
                            "Bash"  { $det = $b.input.command }
                            "Read"  { $det = $b.input.file_path }
                            "Edit"  { $det = $b.input.file_path }
                            "Write" { $det = $b.input.file_path }
                            "Grep"  { $det = $b.input.pattern }
                            "Glob"  { $det = $b.input.pattern }
                            default { $det = ($b.input | ConvertTo-Json -Compress -Depth 3) }
                        }
                        if ($det.Length -gt 160) { $det = $det.Substring(0,160) + "..." }
                        Write-Host ("`n  -> $nombre  ") -ForegroundColor Cyan -NoNewline
                        Write-Host $det -ForegroundColor DarkCyan
                    }
                }
            }
        }
        "user" {
            # Resultado de una herramienta (salida de comando, contenido leido, etc.)
            foreach ($b in $e.message.content) {
                if ($b.type -eq "tool_result") {
                    $txt = ""
                    if ($b.content -is [string]) { $txt = $b.content }
                    elseif ($b.content) { $txt = ($b.content | ForEach-Object { $_.text }) -join "`n" }
                    if ($txt) {
                        $lineas = $txt -split "`n" | Select-Object -First 6
                        foreach ($l in $lineas) { Write-Host ("       | " + $l) -ForegroundColor DarkGray }
                        $total = ($txt -split "`n").Count
                        if ($total -gt 6) { Write-Host ("       | ... (+$($total-6) lineas)") -ForegroundColor DarkGray }
                    }
                }
            }
        }
        "result" {
            $costo = if ($e.total_cost_usd) { " | costo: `$$([math]::Round($e.total_cost_usd,4))" } else { "" }
            $dur   = if ($e.duration_ms) { " | " + [int]($e.duration_ms/1000) + "s" } else { "" }
            Write-Host ("`n  [fin de respuesta$dur$costo]") -ForegroundColor Green
        }
    }
}

if (-not (Get-Command claude -ErrorAction SilentlyContinue)) {
    Write-Host "ERROR: no encuentro 'claude' en el PATH." -ForegroundColor Red; exit 1
}

$ramaActual = (git rev-parse --abbrev-ref HEAD).Trim()
if ($ramaActual -eq "main" -or $ramaActual -eq "master") {
    Log "Estas en '$ramaActual'. Creando rama $RamaTrabajo" "Yellow"
    git checkout -b $RamaTrabajo 2>&1 | Out-Null
    $ramaActual = (git rev-parse --abbrev-ref HEAD).Trim()
    if ($ramaActual -eq "main" -or $ramaActual -eq "master") {
        Write-Host "ERROR: no pude cambiar de rama. Abortando." -ForegroundColor Red; exit 1
    }
}

if (git status --porcelain) {
    Write-Host "ERROR: hay cambios sin commitear. Commitea o descarta antes de arrancar." -ForegroundColor Red
    git status --short; exit 1
}

$commitInicial = (git rev-parse HEAD).Trim()
Log "=== ARRANCA CICLO NOCTURNO (vista en vivo) ===" "Cyan"
Log "Rama: $ramaActual | Commit inicial: $($commitInicial.Substring(0,7))" "Cyan"
Log "Para detener: New-Item STOP -ItemType File" "Cyan"

$i = 0
$fallosSeguidos = 0

while ($i -lt $MaxIteraciones) {

    if (Test-Path (Join-Path $ProyectoDir "STOP")) { Log "STOP detectado. Terminando." "Yellow"; break }

    $i++
    $logIter = Join-Path $LogDir ("iter-{0:D3}-{1}.jsonl" -f $i, (Get-Date -Format 'HHmmss'))
    Write-Host ""
    Log "========== Iteracion $i ==========" "Green"

    $inicio = Get-Date

    # stream-json + verbose = emite un JSON por linea con pensamiento, herramientas y resultados.
    # Cada linea se guarda cruda en el .jsonl Y se renderiza bonita en consola.
    & claude -p $Prompt --dangerously-skip-permissions --output-format stream-json --verbose 2>&1 |
        ForEach-Object {
            Add-Content -Path $logIter -Value $_
            Render-StreamLine $_
        }
    $codigo = $LASTEXITCODE
    $duracion = [int]((Get-Date) - $inicio).TotalSeconds

    Write-Host ""
    Log "Iteracion $i termino en ${duracion}s (exit=$codigo)" "Gray"

    if ($codigo -ne 0 -and $duracion -lt 10) {
        Log "Fallo instantaneo (${duracion}s): NO es limite, es error de configuracion." "Red"
        Get-Content $logIter -Tail 15 | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
        Log "Corrigelo y reinicia. Deteniendo." "Red"; break
    }

    if ($codigo -ne 0 -and $duracion -lt 90) {
        Log "Fallo rapido (posible limite de uso). Espero 15 min y reintento..." "Yellow"
        $i--; Start-Sleep -Seconds $EsperaLimite; continue
    }

    if ($codigo -ne 0) {
        $fallosSeguidos++
        Log "Error (exit=$codigo). Fallos seguidos: $fallosSeguidos" "Yellow"
        if ($fallosSeguidos -ge 5) { Log "5 fallos seguidos. Deteniendo." "Red"; break }
    } else { $fallosSeguidos = 0 }

    if (git status --porcelain) {
        Log "Cambios sin commitear -> commit de seguridad." "Yellow"
        git add -A 2>&1 | Out-Null
        git commit -m "chore(autonomo): cambios sin commitear al cierre de la iteracion $i" 2>&1 | Out-Null
    }

    Start-Sleep -Seconds $PausaEntreIter
}

Write-Host ""
Log "=== CICLO TERMINADO ===" "Cyan"
$commits = (git rev-list --count "$commitInicial..HEAD").Trim()
Log "Iteraciones: $i | Commits nuevos: $commits" "Cyan"
Log "Revisa: git log --oneline $($commitInicial.Substring(0,7))..HEAD" "Cyan"