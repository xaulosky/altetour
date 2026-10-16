@echo off
setlocal
title Alte Tour - Sitio local
cd /d "%~dp0"
where node >nul 2>&1
if errorlevel 1 (
    echo No se encontro Node.js. Instala Node.js 22.12 o superior y vuelve a abrir este archivo.
    pause
    exit /b 1
)
node "%~dp0scripts\start-local.mjs" %*
if errorlevel 1 (
    echo.
    echo No fue posible iniciar el sitio. Revisa el mensaje anterior.
    pause
    exit /b 1
)
endlocal
